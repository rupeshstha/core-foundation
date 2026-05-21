<?php

namespace CoreFoundation\Analysis\Output;

use CoreFoundation\Analysis\MethodMetrics;
use Illuminate\Console\Command;

/**
 * TableRenderer
 *
 * Renders MethodMetrics results as a console table with colour-coded smell scores.
 *
 * Score colour coding (matches the spirit of smelly-code-detector output):
 *   < 30   → no colour (acceptable)
 *   30-59  → yellow (warning)
 *   60-99  → red (problematic)
 *   ≥ 100  → bright red (critical)
 */
final class TableRenderer
{
    public function render(Command $command, array $metrics, int $threshold, int $limit): void
    {
        $filtered = array_filter($metrics, fn ($m) => $m->smellScore >= $threshold);
        $limited  = array_slice(array_values($filtered), 0, $limit);

        if (empty($limited)) {
            $command->info("  No methods found above smell threshold of {$threshold}.");
            return;
        }

        $rows = array_map(fn (MethodMetrics $m) => [
            $this->truncate($m->file),
            $m->displayName(),
            $m->visibility,
            $m->loc,
            $m->arguments,
            $m->cyclomaticComplexity,
            $this->colourScore($m->smellScore),
        ], $limited);

        $command->table(
            headers: ['File', 'Method', 'Visibility', 'LOC', 'Args', 'CCN', 'Smell'],
            rows:    $rows,
        );

        $total = count($filtered);
        if ($total > $limit) {
            $command->line("  <comment>Showing {$limit} of {$total} methods above threshold.</comment>");
        }

        $this->renderSummary($command, $metrics, $threshold);
    }

    private function renderSummary(Command $command, array $all, int $threshold): void
    {
        $above    = count(array_filter($all, fn ($m) => $m->smellScore >= $threshold));
        $critical = count(array_filter($all, fn ($m) => $m->smellScore >= 100));
        $warning  = count(array_filter($all, fn ($m) => $m->smellScore >= 30 && $m->smellScore < 100));

        $command->newLine();
        $command->line('  Summary:');
        $command->line("    Total methods analysed : <comment>" . count($all) . "</comment>");
        $command->line("    Above threshold ({$threshold}) : <comment>{$above}</comment>");
        $command->line("    Warning  (30–99)       : <comment>{$warning}</comment>");
        $command->line("    Critical (≥100)        : <error>{$critical}</error>");
    }

    private function colourScore(int $score): string
    {
        return match (true) {
            $score >= 100 => "<error>{$score}</error>",
            $score >= 60  => "<fg=red>{$score}</>",
            $score >= 30  => "<comment>{$score}</comment>",
            default       => (string) $score,
        };
    }

    private function truncate(string $path, int $maxLength = 55): string
    {
        $relative = str_replace(base_path() . DIRECTORY_SEPARATOR, '', $path);
        return strlen($relative) > $maxLength
            ? '...' . substr($relative, -($maxLength - 3))
            : $relative;
    }
}
