<?php

namespace CoreFoundation\Providers;

use Composer\ClassMapGenerator\ClassMapGenerator;
use CoreFoundation\Attributes\BatchRegistrar;
use CoreFoundation\Facades\Services\ServerTimingFacadeService;
use Illuminate\Support\ServiceProvider;
use ReflectionClass;
use Symfony\Component\Stopwatch\Stopwatch;

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

            $this->publishes([
                __DIR__.'/../../config/interceptors.php' => config_path('interceptors.php'),
            ], 'interceptors');

            $this->loadMigrationsFrom([
                __DIR__.'/../database/migrations',
            ]);
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
        $this->mergeConfigFrom(__DIR__.'/../../config/interceptors.php', 'interceptors');

        include_once __DIR__.'/../Helpers/helpers.php';

        $this->app->singleton(ServerTimingFacadeService::class, function ($app) {
            return new ServerTimingFacadeService(new Stopwatch);
        });

        $this->batchRegistrar([
            __DIR__.'/../Repositories', // test bulk bind
        ]);
    }

    private function bindServices(): void
    {
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
                /** @var \ReflectionAttribute $bind */
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
}
