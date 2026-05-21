<?php

namespace CoreFoundation\Console\Commands;

use CoreFoundation\Analysis\Analyser;
use CoreFoundation\Analysis\Output\JsonRenderer;
use CoreFoundation\Analysis\Output\TableRenderer;
use Illuminate\Console\Command;

/**
 * AnalyseCommand — php artisan core:analyse
 *
 * Analyses PHP method complexity and code smell scores using CoreFoundation's
 * built-in static analyser. No external binary required.
 *
 * Built on nikic/php-parser (already a Laravel dev dependency via PHPStan, Pint).
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ METRICS                                                                     │
 * │                                                                             │
 * │ LOC  — Lines of code in the method (start to end line)                     │
 * │ Args — Number of declared parameters                                        │
 * │ CCN  — Cyclomatic complexity (decision paths: if, else, for, &&, ||, ?)    │
 * │ Smell — (CCN + Args) × LOC — the original php-smelly-code-detector formula │
 * │                                                                             │
 * │ Score guide:                                                                │
 * │   < 30  — acceptable                                                        │
 * │   30–59 — worth reviewing                                                   │
 * │   60–99 — problematic, refactor candidate                                  │
 * │   ≥ 100 — critical, high priority refactor                                 │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * USAGE:
 *   php artisan core:analyse
 *   php artisan core:analyse --path=src/Services
 *   php artisan core:analyse --path=src/Services --path=src/Repositories
 *   php artisan core:analyse --threshold=20 --sort=ccn --limit=50
 *   php artisan core:analyse --visibility=public --exclude-constructors
 *   php artisan core:analyse --format=json > analysis.json
 *   php artisan core:analyse --format=json | jq '.[] | select(.smell_score > 60)'
 */
class AnalyseCommand extends Command
{
    protected $signature = 'core:analyse
        {--path=*          : Path(s) to analyse — can be used multiple times (overrides config)}
        {--threshold=      : Minimum smell score to display (default: config profiling.quality.smell_threshold)}
        {--max=            : Maximum smell score to display}
        {--sort=smell      : Sort by: smell, loc, arg, ccn}
        {--limit=30        : Maximum results to display}
        {--visibility=     : Filter by: public, protected, private}
        {--exclude-constructors : Hide __construct methods}
        {--format=table    : Output format: table, json}
        {--baseline=       : Path to a JSON baseline file — only show regressions}
    ';

    protected $description = 'Analyse PHP code quality: complexity, smell score, and argument counts';

    public function handle(Analyser $analyser): int
    {
        $paths     = $this->resolvePaths();
        $threshold = (int) ($this->option('threshold') ?? config('profiling.quality.smell_threshold', 30));
        $limit     = (int) ($this->option('limit') ?? 30);
        $sortBy    = (string) ($this->option('sort') ?? 'smell');
        $format    = (string) ($this->option('format') ?? 'table');
        $visibility = $this->option('visibility') ?: null;
        $excludeConstructors = (bool) $this->option('exclude-constructors');

        if ($format === 'table') {
            $this->info('CoreFoundation Code Analyser');
            $this->line('  Analysing: ' . implode(', ', array_map(
                fn ($p) => str_replace(base_path() . '/', '', $p),
                $paths
            )));
            $this->newLine();
        }

        $metrics = $analyser->analyse(
            paths:               $paths,
            excludePaths:        config('profiling.quality.exclude', ['vendor', 'tests']),
            sortBy:              $sortBy,
            visibility:          $visibility,
            excludeConstructors: $excludeConstructors,
        );

        if (empty($metrics)) {
            $this->warn('  No PHP files found in the specified paths.');
            return self::FAILURE;
        }

        // Apply max filter
        if ($this->option('max') !== null) {
            $max     = (int) $this->option('max');
            $metrics = array_filter($metrics, fn ($m) => $m->smellScore <= $max);
        }

        // Baseline regression check
        $baseline = $this->option('baseline');
        if ($baseline !== null) {
            $metrics = $this->filterByBaseline($metrics, $baseline);
        }

        // Render output
        if ($format === 'json') {
            (new JsonRenderer)->render($this, $metrics, $threshold, $limit);
        } else {
            (new TableRenderer)->render($this, $metrics, $threshold, $limit);
            $this->newLine();
            $this->line('  <fg=gray>Tip: run with --format=json to pipe results to jq or save as baseline.</>');
            $this->line('  <fg=gray>     php artisan core:analyse --format=json > .smell-baseline.json</>');
            $this->line('  <fg=gray>     php artisan core:analyse --baseline=.smell-baseline.json</>');
        }

        // Return failure if any critical methods found (useful in CI)
        $hasCritical = ! empty(array_filter($metrics, fn ($m) => $m->smellScore >= 100));

        return $hasCritical ? self::FAILURE : self::SUCCESS;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    private function resolvePaths(): array
    {
        $optionPaths = $this->option('path');

        if (! empty($optionPaths)) {
            return array_map(fn ($p) => str_starts_with($p, '/') ? $p : base_path($p), $optionPaths);
        }

        $configPaths = config('profiling.quality.paths', ['src', 'app']);

        return array_map(fn ($p) => base_path($p), $configPaths);
    }

    /**
     * Filter metrics to only show regressions since the baseline.
     * A regression is a method whose smell score increased since the baseline was captured.
     */
    private function filterByBaseline(array $metrics, string $baselinePath): array
    {
        if (! file_exists($baselinePath)) {
            $this->warn("  Baseline file not found: {$baselinePath}");
            $this->line('  <fg=gray>Run without --baseline first to generate one.</>');
            return $metrics;
        }

        $raw      = json_decode(file_get_contents($baselinePath), true) ?? [];
        $baseline = [];

        foreach ($raw as $entry) {
            $key = ($entry['class'] ?? '') . '::' . ($entry['method'] ?? '');
            $baseline[$key] = $entry['smell_score'] ?? 0;
        }

        $regressions = array_filter($metrics, function ($m) use ($baseline) {
            $key          = $m->class . '::' . $m->method;
            $baselineScore = $baseline[$key] ?? null;

            // New method (not in baseline) or score increased = regression
            return $baselineScore === null || $m->smellScore > $baselineScore;
        });

        if (empty($regressions)) {
            $this->info('  No regressions found since baseline.');
        } else {
            $this->warn('  Showing only regressions since baseline.');
        }

        return array_values($regressions);
    }
}
