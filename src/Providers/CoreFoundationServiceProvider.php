<?php

namespace CoreFoundation\Providers;

use Throwable;
use ReflectionClass;
use ReflectionAttribute;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Stopwatch\Stopwatch;
use CoreFoundation\Attributes\BatchRegistrar;
use Illuminate\Foundation\Exceptions\Handler;
use CoreFoundation\Exceptions\ExceptionRenderer;
use Composer\ClassMapGenerator\ClassMapGenerator;
use CoreFoundation\Console\Commands\GenerateApiDocs;
use Illuminate\Foundation\Configuration\Exceptions;
use CoreFoundation\Console\Commands\MakeModuleCommand;
use CoreFoundation\Facades\Services\ServerTimingFacadeService;

class CoreFoundationServiceProvider extends ServiceProvider
{
    protected static $bindable = [];

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/core_foundation.php' => config_path('core_foundation.php'),
            ], 'core_foundation');

            $this->loadMigrationsFrom([
                __DIR__.'/../database/migrations',
            ]);

            $this->commands([
                GenerateApiDocs::class,
                MakeModuleCommand::class,
            ]);

            $this->publishes([
                __DIR__.'/../../Config/api-docs.php' => config_path('api-docs.php'),
            ], 'core-foundation-api-docs');
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->app->register(AppMonitorServiceProvider::class);

        $this->bindServices();
        $this->mergeConfigFrom(__DIR__.'/../../config/core_foundation.php', 'core_foundation');

        include_once __DIR__.'/../Helpers/helpers.php';

        $this->batchRegistrar([
            __DIR__.'/../Repositories', // test bulk bind
        ]);
    }

    private function bindServices(): void {}

    public function batchRegistrar(array $batchRegistrarPaths): void
    {
        foreach ($batchRegistrarPaths as $batchRegistrarPath) {
            if (! isset(static::$bindable[$batchRegistrarPath])) {
                static::$bindable[$batchRegistrarPath] = ClassMapGenerator::createMap(realpath($batchRegistrarPath));
            }
            $classMap = static::$bindable[$batchRegistrarPath];
            foreach ($classMap as $namespace => $realPath) {
                $attributes = $this->getBindAttributes($namespace);
                /** @var ReflectionAttribute $bind */
                foreach ($attributes as $bind) {
                    $implement = $bind->getArguments()[0] ?? null;
                    if ($implement) {
                        $this->app->singleton($namespace, $implement);
                    }
                }
            }
        }
    }

    private function getBindAttributes(string $port): array
    {
        $reflectionClass = new ReflectionClass($port);

        return $reflectionClass->getAttributes(BatchRegistrar::class);
    }

    /**
     * Wire ExceptionRenderer into Laravel's withExceptions() pipeline.
     *
     * Uses app()->withExceptions() which is the correct way to register
     * exception handlers from a ServiceProvider in Laravel 11+.
     *
     * This is safe to call multiple times — Laravel deduplicates renderers
     * internally based on the closure's type-hints.
     */
    private function registerExceptionHandling(): void
    {
        $this->app->make(Handler::class)
            ->renderable(function (Throwable $e, $request) {
                // Delegate entirely to ExceptionRenderer — it handles
                // all type-specific rendering internally.
                return null;
            });

        // The correct Laravel 11+ way to register from a ServiceProvider
        if (method_exists($this->app, 'withExceptions')) {
            $this->app->withExceptions(
                fn (Exceptions $exceptions) => ExceptionRenderer::register($exceptions)
            );
        }
    }
}
