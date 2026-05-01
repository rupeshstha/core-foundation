<?php

namespace CoreFoundation\Providers;

use Composer\ClassMapGenerator\ClassMapGenerator;
use CoreFoundation\Attributes\BatchRegistrar;
use CoreFoundation\Console\GenerateFactoryCommand;
use CoreFoundation\Contracts\StrategyContract;
use CoreFoundation\Facades\Services\ServerTimingFacadeService;
use CoreFoundation\Listeners\RepositoryEventListener;
use CoreFoundation\Services\InterceptTestService;
use CoreFoundation\Services\StrategyService;
use CoreFoundation\Services\TestFacadeDoc;
use CoreFoundation\Services\TestService;
use Illuminate\Support\Facades\Event;
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
        $this->app->register(LicensingServiceProvider::class);

        $this->bindServices();
        $this->mergeConfigFrom(__DIR__.'/../../config/core_foundation.php', 'core_foundation');
        $this->mergeConfigFrom(__DIR__.'/../../config/interceptors.php', 'interceptors');

        include_once __DIR__.'/../Helpers/helpers.php';
        // TODO feature is incomplete.
        // $this->app->singleton("doc", TestFacadeDoc::class);

        // $this->app->bind('command.test-factory-helper.generate', function ($app) {
        //     return new GenerateFactoryCommand($app['files'], $app['view']);
        // });
        // $this->commands('command.test-factory-helper.generate');

        $this->app->singleton(ServerTimingFacadeService::class, function ($app) {
            return new ServerTimingFacadeService(new Stopwatch);
        });

        $this->batchRegistrar([
            __DIR__.'/../Repositories', // test bulk bind
        ]);
        // Event::listen("index.before", RepositoryEventListener::class);

        // TestService::setFactory(InterceptTestService::class);
        // TestService::setFactoryCondition(InterceptTestService::class, function () {
        //     return true;
        // });
    }

    private function bindServices(): void
    {
        // services bing
        $this->app->bind(
            abstract: StrategyContract::class,
            concrete: StrategyService::class
        );
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
