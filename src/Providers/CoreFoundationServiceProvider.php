<?php

namespace CoreFoundation\Providers;

use InvalidArgumentException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\ServiceProvider;
use Illuminate\Foundation\Exceptions\Handler;
use CoreFoundation\Console\Commands\WarmCache;
use CoreFoundation\Exceptions\ExceptionRenderer;
use Illuminate\Foundation\Configuration\Exceptions;
use CoreFoundation\Console\Commands\GenerateApiDocs;
use CoreFoundation\Console\Commands\MakeModuleCommand;
use CoreFoundation\Repositories\Cache\RepositoryCache;
use CoreFoundation\Repositories\Cache\CacheBustCollector;
use CoreFoundation\Support\Maintenance\MaintenanceManager;
use CoreFoundation\Repositories\Cache\CacheWarmingRegistry;

class CoreFoundationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        $this->registerExceptionHandling();
        $this->assertCacheDriverSupportsTags();

        $this->loadTranslationsFrom(__DIR__.'/../../resources/lang', 'core-foundation');

        // Load routes from the package.
        if (file_exists(__DIR__.'/../../routes/features.php')) {
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
                __DIR__.'/../../resources/lang' => lang_path('vendor/core-foundation'),
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
        $this->app->singleton(MaintenanceManager::class);
    }

    /**
     * Repository and service caching both go through Cache::tags() — only
     * array, redis, and memcached implement it. file and database define no
     * tags() method at all and throw BadMethodCallException deep inside
     * Laravel's cache internals the first time any cache helper runs. Fail
     * here instead, with a message that says exactly what to change.
     */
    private function assertCacheDriverSupportsTags(): void
    {
        if (! (bool) config('core-foundation.cache.global', true)) {
            return;
        }

        $store = Cache::getStore();

        throw_unless(
            condition: method_exists($store, 'tags'),
            exception: new InvalidArgumentException(
                'CoreFoundation repository and service caching require a tag-capable cache driver. '
                .'The configured driver ['.config('cache.default').'] does not support Cache::tags() '
                .'and will throw on the first repository read or write. Set CACHE_STORE to redis, '
                .'memcached, or array — or set core-foundation.cache.global to false to disable '
                .'repository caching entirely.'
            ),
        );
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
