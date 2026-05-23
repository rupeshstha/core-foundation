<?php

namespace CoreFoundation\Analysis\Visitors;

use Closure;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\NodeVisitorAbstract;
use PhpParser\Node\Stmt\ClassMethod;
use CoreFoundation\Analysis\MethodMetrics;

/**
 * MetricsVisitor
 *
 * Visits every class method in a PHP file and computes all metrics in one pass.
 * Uses nikic/php-parser's NodeVisitor pattern.
 *
 * Metrics computed per method:
 *   loc  — non-empty lines in method body (start line to end line)
 *   arg  — declared parameter count
 *   ccn  — cyclomatic complexity (decision nodes + 1 base)
 *   smell — (ccn + arg) * loc
 */
final class MetricsVisitor extends NodeVisitorAbstract
{
    /** @var MethodMetrics[] */
    private array $results = [];

    private ?string $currentClass = null;

    private string $currentFile = '';

    /**
     * AST node types that increment cyclomatic complexity.
     * Each represents a decision point that creates a new execution path.
     */
    private const COMPLEXITY_NODES = [
        Node\Stmt\If_::class,
        Node\Stmt\ElseIf_::class,
        Node\Stmt\Case_::class,
        Node\Stmt\For_::class,
        Node\Stmt\Foreach_::class,
        Node\Stmt\While_::class,
        Node\Stmt\Do_::class,
        Node\Stmt\Catch_::class,
        Node\Expr\BinaryOp\BooleanAnd::class,
        Node\Expr\BinaryOp\BooleanOr::class,
        Node\Expr\BinaryOp\LogicalAnd::class,
        Node\Expr\BinaryOp\LogicalOr::class,
        Node\Expr\BinaryOp\Coalesce::class,
        Node\Expr\Ternary::class,
        Node\Expr\NullsafeMethodCall::class,
        Node\Expr\NullsafePropertyFetch::class,
        Node\MatchArm::class,
    ];

    public function setFile(string $file): void
    {
        $this->currentFile = $file;
        $this->results = [];
        $this->currentClass = null;
    }

    /** @return MethodMetrics[] */
    public function getResults(): array
    {
        return $this->results;
    }

    public function enterNode(Node $node): null
    {
        if ($node instanceof Class_) {
            $this->currentClass = $node->namespacedName?->toString()
                ?? ($node->name?->toString() ?? 'Anonymous');
        }

        if ($node instanceof ClassMethod) {
            $this->results[] = $this->computeMetrics($node);
        }

        return null;
    }

    public function leaveNode(Node $node): null
    {
        if ($node instanceof Class_) {
            $this->currentClass = null;
        }

        return null;
    }

    private function computeMetrics(ClassMethod $method): MethodMetrics
    {
        $loc = $this->computeLoc($method);
        $arguments = count($method->params);
        $ccn = $this->computeCyclomaticComplexity($method);
        $smellScore = ($ccn + $arguments) * max($loc, 1);

        return new MethodMetrics(
            class: $this->currentClass ?? 'Unknown',
            method: $method->name->toString(),
            visibility: $this->resolveVisibility($method),
            file: $this->currentFile,
            loc: $loc,
            arguments: $arguments,
            cyclomaticComplexity: $ccn,
            smellScore: $smellScore,
        );
    }

    private function computeLoc(ClassMethod $method): int
    {
        $start = $method->getStartLine();
        $end = $method->getEndLine();

        if ($start === -1 || $end === -1) {
            return 0;
        }

        return max(0, $end - $start);
    }

    private function computeCyclomaticComplexity(ClassMethod $method): int
    {
        $complexity = 1;

        $this->walkNodes($method->stmts ?? [], function (Node $node) use (&$complexity): void {
            foreach (self::COMPLEXITY_NODES as $type) {
                if ($node instanceof $type) {
                    $complexity++;
                    break;
                }
            }
        });

        return $complexity;
    }

    private function walkNodes(array $nodes, Closure $callback): void
    {
        foreach ($nodes as $node) {
            if (! $node instanceof Node) {
                continue;
            }

            $callback($node);

            foreach ($node->getSubNodeNames() as $name) {
                $child = $node->$name;
                if ($child instanceof Node) {
                    $this->walkNodes([$child], $callback);
                } elseif (is_array($child)) {
                    $this->walkNodes($child, $callback);
                }
            }
        }
    }

    private function resolveVisibility(ClassMethod $method): string
    {
        return match (true) {
            $method->isPublic() => 'public',
            $method->isProtected() => 'protected',
            $method->isPrivate() => 'private',
            default => 'public',
        };
    }
}
