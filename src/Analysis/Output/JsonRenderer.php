<?php

namespace CoreFoundation\Analysis\Output;

use CoreFoundation\Analysis\MethodMetrics;
use Illuminate\Console\Command;

/**
 * JsonRenderer
 *
 * Renders MethodMetrics as JSON — for CI pipelines, scripts, and tooling integrations.
 *
 * Output is written to stdout so it can be piped:
 *   php artisan core:analyse --format=json > analysis.json
 *   php artisan core:analyse --format=json | jq '.[] | select(.smell_score > 50)'
 */
final class JsonRenderer
{
    public function render(Command $command, array $metrics, int $threshold, int $limit): void
    {
        $filtered = array_filter($metrics, fn ($m) => $m->smellScore >= $threshold);
        $limited  = array_slice(array_values($filtered), 0, $limit);

        $output = array_map(fn (MethodMetrics $m) => $m->toArray(), $limited);

        $command->line(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
