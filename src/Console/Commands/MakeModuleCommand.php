<?php

namespace CoreFoundation\Console\Commands;

use RuntimeException;
use Illuminate\Support\Str;
use Illuminate\Console\Command;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;
use function Laravel\Prompts\confirm;
use function Laravel\Prompts\warning;
use function Laravel\Prompts\multiselect;

/**
 * MakeModuleCommand — php artisan core:make {Name}
 *
 * Interactive scaffold generator for CoreFoundation-compliant modules.
 * Generates only the components the developer selects, writes stubs to the
 * correct paths, and skips files that already exist.
 *
 * USAGE:
 *
 *   php artisan core:make Order
 *   php artisan core:make          # interactive name prompt
 *
 * CRUD preset:
 *   model, factory, repository, service, controller, store-request,
 *   update-request, resource, collection, data-object, policy, provider, test
 *
 * Custom Feature preset:
 *   service, data-object, test
 */
class MakeModuleCommand extends Command
{
    protected $signature = 'core:make {name? : Singular PascalCase class name (e.g. Order)}';

    protected $description = 'Scaffold a CoreFoundation-compliant module interactively';

    private const COMPONENTS = [
        'model' => 'Model',
        'factory' => 'Factory',
        'repository' => 'Repository',
        'service' => 'Service',
        'controller' => 'Controller',
        'store-request' => 'Store Request',
        'update-request' => 'Update Request',
        'resource' => 'Resource',
        'collection' => 'Collection',
        'data-object' => 'Data Object',
        'policy' => 'Policy',
        'observer' => 'Observer',
        'provider' => 'Service Provider',
        'test' => 'Feature Test',
    ];

    private const CRUD_PRESET = [
        'model', 'factory', 'repository', 'service', 'controller',
        'store-request', 'update-request', 'resource', 'collection',
        'data-object', 'policy', 'provider', 'test',
    ];

    private const CUSTOM_PRESET = ['service', 'data-object', 'test'];

    // =========================================================================
    // Handle
    // =========================================================================

    public function handle(): int
    {
        $name = $this->resolveName();

        $type = select(
            label: 'What are you building?',
            options: [
                'crud' => 'CRUD — model, controller, service, repository, resource, test',
                'custom' => 'Custom Feature — service, data object, test only',
            ],
            default: 'crud',
        );

        $selected = multiselect(
            label: 'Components to generate',
            options: self::COMPONENTS,
            default: $type === 'crud' ? self::CRUD_PRESET : self::CUSTOM_PRESET,
            required: true,
            hint: 'Space to toggle, Enter to confirm',
        );

        $rootNamespace = text(
            label: 'Root namespace',
            default: 'App',
            required: true,
            hint: 'e.g. App — becomes App\Modules\Order or App\Models\Order',
        );

        $useModuleStructure = confirm(
            label: 'Use module folder structure?',
            default: true,
            hint: 'Yes → app/Modules/'.$name.'/  |  No → app/',
        );

        [$moduleNamespace, $modulePath] = $this->resolveModulePaths(
            $name,
            $rootNamespace,
            $useModuleStructure,
        );

        $this->newLine();

        $this->generateFiles($name, $selected, $moduleNamespace, $modulePath);

        $this->showNextSteps($name, $modulePath, $selected);

        return self::SUCCESS;
    }

    // =========================================================================
    // File generation
    // =========================================================================

    private function generateFiles(
        string $name,
        array $selected,
        string $moduleNamespace,
        string $modulePath,
    ): void {
        $vars = $this->buildVars($name, $moduleNamespace);
        $map = $this->componentMap($name, $modulePath);

        $generated = [];
        $skipped = [];

        foreach ($selected as $component) {
            [$stubFile, $targetPath] = $map[$component];

            $fullPath = base_path($targetPath);
            $dir = dirname($fullPath);

            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            if (file_exists($fullPath)) {
                $skipped[] = $targetPath;

                continue;
            }

            file_put_contents($fullPath, $this->renderStub($stubFile, $vars));
            $generated[] = $targetPath;
        }

        if (! empty($generated)) {
            info('Generated '.count($generated).' file(s):');
            foreach ($generated as $path) {
                $this->line("  <fg=green>✓</> {$path}");
            }
        }

        if (! empty($skipped)) {
            $this->newLine();
            warning('Skipped '.count($skipped).' file(s) — already exist:');
            foreach ($skipped as $path) {
                $this->line("  <fg=yellow>!</> {$path}");
            }
        }
    }

    private function renderStub(string $stubFile, array $vars): string
    {
        $stubPath = __DIR__.'/../Stubs/'.$stubFile;

        if (! file_exists($stubPath)) {
            throw new RuntimeException("Stub not found: {$stubPath}");
        }

        return str_replace(array_keys($vars), array_values($vars), file_get_contents($stubPath));
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    private function resolveName(): string
    {
        $arg = $this->argument('name');
        $raw = is_string($arg) ? $arg : text(
            label: 'Class name',
            placeholder: 'Order',
            required: true,
            hint: 'Singular PascalCase — e.g. Order, UserProfile',
        );

        return Str::studly($raw);
    }

    private function resolveModulePaths(string $name, string $rootNamespace, bool $useModule): array
    {
        if ($useModule) {
            return [
                $rootNamespace.'\\Modules\\'.$name,
                'app/Modules/'.$name,
            ];
        }

        return [
            $rootNamespace,
            'app',
        ];
    }

    private function buildVars(string $name, string $namespace): array
    {
        return [
            '{{ namespace }}' => $namespace,
            '{{ class }}' => $name,
            '{{ modelLower }}' => Str::snake($name),
            '{{ modelVariable }}' => Str::camel($name),
            '{{ modelPlural }}' => Str::plural(Str::snake($name)),
            '{{ table }}' => Str::plural(Str::snake($name)),
            '{{ routePrefix }}' => Str::plural(Str::kebab($name)),
        ];
    }

    private function componentMap(string $name, string $modulePath): array
    {
        return [
            'model' => ['model.stub',         "{$modulePath}/Models/{$name}.php"],
            'factory' => ['factory.stub',        "database/factories/{$name}Factory.php"],
            'repository' => ['repository.stub',     "{$modulePath}/Repositories/{$name}Repository.php"],
            'service' => ['service.stub',        "{$modulePath}/Services/{$name}Service.php"],
            'controller' => ['controller.stub',     "{$modulePath}/Http/Controllers/{$name}Controller.php"],
            'store-request' => ['request.store.stub',  "{$modulePath}/Http/Requests/Store{$name}Request.php"],
            'update-request' => ['request.update.stub', "{$modulePath}/Http/Requests/Update{$name}Request.php"],
            'resource' => ['resource.stub',       "{$modulePath}/Resources/{$name}Resource.php"],
            'collection' => ['collection.stub',     "{$modulePath}/Resources/{$name}Collection.php"],
            'data-object' => ['data-object.stub',    "{$modulePath}/DataObjects/{$name}Data.php"],
            'policy' => ['policy.stub',         "{$modulePath}/Policies/{$name}Policy.php"],
            'observer' => ['observer.stub',       "{$modulePath}/Observers/{$name}Observer.php"],
            'provider' => ['provider.stub',       "{$modulePath}/Providers/{$name}ServiceProvider.php"],
            'test' => ['test.stub',           "tests/Feature/{$name}Test.php"],
        ];
    }

    private function showNextSteps(string $name, string $modulePath, array $selected): void
    {
        $steps = [];

        if (in_array('provider', $selected, true)) {
            $steps[] = "Register {$name}ServiceProvider in bootstrap/app.php";
        }

        if (in_array('controller', $selected, true)) {
            $steps[] = "Add routes for {$name}Controller in routes/api.php";
        }

        if (in_array('model', $selected, true)) {
            $steps[] = 'Create migration: php artisan make:migration create_'.Str::plural(Str::snake($name)).'_table';
        }

        if (in_array('policy', $selected, true)) {
            $steps[] = "Register policy in {$name}ServiceProvider: Gate::policy({$name}::class, {$name}Policy::class)";
        }

        if (in_array('observer', $selected, true)) {
            $steps[] = "Register observer in {$name}ServiceProvider: {$name}::observe({$name}Observer::class)";
        }

        if (! empty($steps)) {
            $this->newLine();
            note(
                "Next steps:\n".implode("\n", array_map(fn ($s) => "  • {$s}", $steps)),
                'What to do next',
            );
        }
    }
}
