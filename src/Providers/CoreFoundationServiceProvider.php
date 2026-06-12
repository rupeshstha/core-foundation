<?php

namespace CoreFoundation\Providers;

use ReflectionClass;
use ReflectionAttribute;
use Laravel\Pennant\Feature;
use Illuminate\Support\ServiceProvider;
use CoreFoundation\Attributes\BatchRegistrar;
use Illuminate\Foundation\Exceptions\Handler;
use CoreFoundation\Exceptions\ExceptionRenderer;
use Composer\ClassMapGenerator\ClassMapGenerator;
use Illuminate\Foundation\Configuration\Exceptions;
use CoreFoundation\Console\Commands\WarmCache;
use CoreFoundation\Console\Commands\GenerateApiDocs;
use CoreFoundation\Console\Commands\MakeModuleCommand;
use CoreFoundation\Repositories\Cache\CacheWarmingRegistry;
use CoreFoundation\Repositories\Cache\CacheBustCollector;
use CoreFoundation\Repositories\Cache\RepositoryCache;

class CoreFoundationServiceProvider extends ServiceProvider
{
    protected static $bindable = [];

    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->registerExceptionHandling();

        $this->loadTranslationsFrom(__DIR__.'/../../lang', 'core-foundation');

        // Only register feature flag routes when Pennant is installed.
        // Prevents a fatal error when the host app hasn't required pennant/pennant.
        if (class_exists(Feature::class)) {
            $this->loadRoutesFrom(__DIR__.'/../../routes/features.php');
        }

        // Migrations must load in all environments (web, CLI, and test suites
        // using RefreshDatabase). Placing this inside runningInConsole() would
        // prevent the features table from being created during automated tests.
        $this->loadMigrationsFrom([
            __DIR__.'/../database/migrations',
        ]);

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../../config/core-foundation.php' => config_path('core-foundation.php'),
            ], 'core-foundation');

            $this->publishes([
                __DIR__.'/../../lang' => lang_path('vendor/core-foundation'),
            ], 'core-foundation-lang');

            $this->commands([
                GenerateApiDocs::class,
                MakeModuleCommand::class,
                WarmCache::class,
            ]);

            $this->publishes([
                __DIR__.'/../../config/api-docs.php' => config_path('api-docs.php'),
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
        $this->mergeConfigFrom(__DIR__.'/../../config/core-foundation.php', 'core-foundation');
        $this->mergeConfigFrom(__DIR__.'/../../config/repository.php', 'repository');

        include_once __DIR__.'/../Helpers/helpers.php';
    }

    private function bindServices(): void
    {
        // Scoped — one instance per request in Octane, behaves as singleton in standard Laravel.
        // CacheBustCollector must be scoped so the same instance is shared between
        // RepositoryCache (writer) and AttachCacheHeaders middleware (reader).
        $this->app->scoped(CacheBustCollector::class);

        // Scoped to ensure RepositoryCache shares the same CacheBustCollector instance
        // as the middleware within the same request lifecycle.
        $this->app->scoped(RepositoryCache::class);

        $this->app->singleton(CacheWarmingRegistry::class);
    }

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
                        // Repositories must always be transient (bind, not singleton).
                        // A singleton repository holds request-scoped state across
                        // Octane requests, which causes cross-request data leaks.
                        $this->app->bind($namespace, $implement);
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
     * Two-phase registration:
     *  1. If the Handler is already resolved (common in tests/Octane), wrap
     *     it directly in an Exceptions instance and register immediately.
     *  2. Also register via afterResolving so it fires for any future
     *     Handler resolution (standard app boot sequence).
     */
    private function registerExceptionHandling(): void
    {
        // Phase 1 — Handler already resolved (tests, Octane, re-boots)
        if ($this->app->resolved(Handler::class)) {
            $handler = $this->app->make(Handler::class);
            ExceptionRenderer::register(new Exceptions($handler));
        }

        // Phase 2 — Register for future resolution (normal app bootstrap)
        $this->app->afterResolving(
            Handler::class,
            fn (Handler $handler) => ExceptionRenderer::register(new Exceptions($handler))
        );
    }
}
