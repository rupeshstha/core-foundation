<?php

namespace CoreFoundation\Analysis;

use Closure;
use Throwable;
use PhpParser\Parser;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use PhpParser\NodeVisitor\NameResolver;
use CoreFoundation\Analysis\Visitors\MetricsVisitor;

/**
 * Analyser
 *
 * Orchestrates the full analysis pipeline:
 *   1. Discover PHP files in the given paths
 *   2. Parse each file into an AST using nikic/php-parser
 *   3. Walk the AST with MetricsVisitor to collect per-method metrics
 *   4. Return sorted, filtered MethodMetrics collection
 *
 * Requires: nikic/php-parser (^5.0) — already a Laravel dev dependency via PHPStan/Pint.
 */
final class Analyser
{
    private readonly Parser $parser;

    private readonly MetricsVisitor $visitor;

    private readonly NodeTraverser $traverser;

    public function __construct()
    {
        $this->parser = (new ParserFactory)->createForHostVersion();
        $this->visitor = new MetricsVisitor;

        // NameResolver resolves namespaced class names so $node->namespacedName is populated
        $this->traverser = new NodeTraverser(new NameResolver, $this->visitor);
    }

    /**
     * Analyse one or more directory paths and return all method metrics.
     *
     * @param  array<string>  $paths  Absolute paths to analyse
     * @param  array<string>  $excludePaths  Directory names to exclude (e.g. ['vendor', 'tests'])
     * @param  string  $sortBy  Column to sort by: smell, loc, arg, ccn
     * @param  string|null  $visibility  Filter by visibility: public, protected, private, null (all)
     * @return MethodMetrics[]
     */
    public function analyse(
        array $paths,
        array $excludePaths = ['vendor', 'tests'],
        string $sortBy = 'smell',
        ?string $visibility = null,
        bool $excludeConstructors = false,
    ): array {
        $results = [];

        foreach ($paths as $path) {
            if (! is_dir($path)) {
                continue;
            }

            $results = array_merge($results, $this->analyseDirectory($path, $excludePaths));
        }

        // Apply filters
        if ($visibility !== null) {
            $results = array_filter($results, fn ($m) => $m->visibility === $visibility);
        }

        if ($excludeConstructors) {
            $results = array_filter($results, fn ($m) => $m->method !== '__construct');
        }

        // Sort
        usort($results, $this->buildSorter($sortBy));

        return $results;
    }

    /**
     * @param  array<string>  $excludePaths
     * @return MethodMetrics[]
     */
    private function analyseDirectory(string $path, array $excludePaths): array
    {
        $finder = (new Finder)
            ->in($path)
            ->name('*.php')
            ->files()
            ->sortByName();

        foreach ($excludePaths as $exclude) {
            $finder->exclude($exclude);
        }

        $results = [];

        foreach ($finder as $file) {
            $filePath = $file->getRealPath();

            try {
                $code = file_get_contents($filePath);

                if ($code === false) {
                    continue;
                }

                $ast = $this->parser->parse($code);

                if ($ast === null) {
                    continue;
                }

                $this->visitor->setFile($filePath);
                $this->traverser->traverse($ast);

                $results = array_merge($results, $this->visitor->getResults());
            } catch (Throwable) {
                // Skip unparseable files silently — don't halt the full analysis
                continue;
            }
        }

        return $results;
    }

    private function buildSorter(string $sortBy): Closure
    {
        return match ($sortBy) {
            'loc' => fn ($a, $b) => $b->loc <=> $a->loc,
            'arg' => fn ($a, $b) => $b->arguments <=> $a->arguments,
            'ccn' => fn ($a, $b) => $b->cyclomaticComplexity <=> $a->cyclomaticComplexity,
            default => fn ($a, $b) => $b->smellScore <=> $a->smellScore, // 'smell'
        };
    }
}
