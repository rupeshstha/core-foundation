<?php

namespace CoreFoundation\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;
use Illuminate\Support\Facades\File;
use CoreFoundation\Console\ApiDocs\SpecBuilder;
use CoreFoundation\Console\ApiDocs\RouteScanner;
use CoreFoundation\Console\ApiDocs\OperationBuilder;
use CoreFoundation\Console\ApiDocs\ReflectionReader;

/**
 * GenerateApiDocs
 *
 * @deprecated Use dedoc/scramble with ScrambleCoreFoundationServiceProvider instead.
 *   Install:   composer require dedoc/scramble the-artisans-foundry/scramble-core-foundation
 *   Remove:    the api:docs command from your workflow.
 *   Docs auto-generate from type hints — no per-class annotations needed.
 *   This command will be removed in a future release.
 */
class GenerateApiDocs extends Command
{
    protected $signature = 'api:docs
                            {--output= : Output directory path (overrides config)}
                            {--format= : Output format: yaml, json, or both (overrides config)}';

    protected $description = 'Generate OpenAPI 3.0 documentation from route attributes and schema definitions';

    public function handle(): int
    {
        $this->info('Scanning routes and reading attributes...');

        $config = config('api-docs', []);

        // CLI options override config
        $outputPath = $this->option('output') ?? $config['output'] ?? storage_path('app/api-docs');
        $format = $this->option('format') ?? $config['format'] ?? 'both';

        // ─── Pipeline ────────────────────────────────────────────────────────

        $scanner = new RouteScanner($config);
        $reader = new ReflectionReader;
        $operationBuilder = new OperationBuilder;
        $specBuilder = new SpecBuilder($config);

        $scannedRoutes = $scanner->scan();
        $this->line('  Found <comment>'.count($scannedRoutes).'</comment> routes matching prefix <comment>'.($config['prefix'] ?? 'api').'</comment>');

        $skipped = 0;
        $documented = 0;

        foreach ($scannedRoutes as $route) {
            $result = $reader->read($route);

            if ($result === null) {
                $this->warn("  Skipping [{$route->method}] {$route->uri} — controller not found.");
                $skipped++;

                continue;
            }

            $operation = $operationBuilder->build($result);
            $specBuilder->addOperation($result, $operation);

            $this->line("  <info>✓</info> [{$route->method}] {$route->uri}");
            $documented++;
        }

        $spec = $specBuilder->build();

        // ─── Write output ─────────────────────────────────────────────────────

        File::ensureDirectoryExists($outputPath);

        $written = [];

        if (in_array($format, ['yaml', 'both'], true)) {
            $yamlPath = $outputPath.'/openapi.yaml';
            $this->writeYaml($spec, $yamlPath);
            $written[] = $yamlPath;
        }

        if (in_array($format, ['json', 'both'], true)) {
            $jsonPath = $outputPath.'/openapi.json';
            $this->writeJson($spec, $jsonPath);
            $written[] = $jsonPath;
        }

        // ─── Summary ─────────────────────────────────────────────────────────

        $this->newLine();
        $this->info('API documentation generated successfully.');
        $this->table(
            ['Metric', 'Count'],
            [
                ['Routes scanned',    count($scannedRoutes)],
                ['Operations written', $documented],
                ['Routes skipped',    $skipped],
            ]
        );

        $this->newLine();
        foreach ($written as $path) {
            $this->line('  <comment>→</comment> '.$path);
        }

        return self::SUCCESS;
    }

    private function writeYaml(array $spec, string $path): void
    {
        // Symfony Yaml component — already available in Laravel
        $yaml = Yaml::dump(
            input: $spec,
            inline: 10,   // depth before switching to inline style
            indent: 2,
            flags: Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE,
        );

        File::put($path, $yaml);
    }

    private function writeJson(array $spec, string $path): void
    {
        File::put($path, json_encode($spec, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}
