<?php

namespace CoreFoundation\Providers;

use CoreFoundation\Console\GenerateFactoryCommand;
use CoreFoundation\Console\WatchQueryCommand;
use CoreFoundation\Contracts\StrategyContract;
use CoreFoundation\Facades\Services\ServerTimingFacadeService;
use CoreFoundation\Listeners\RepositoryEventListener;
use CoreFoundation\Services\InterceptTestService;
use CoreFoundation\Services\StrategyService;
use CoreFoundation\Services\TestFacadeDoc;
use CoreFoundation\Services\TestService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class CoreFoundationServiceProvider extends ServiceProvider
{
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
                __DIR__ . '/../database/migrations'
            ]);

            $this->commands([
                WatchQueryCommand::class,
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
        $this->mergeConfigFrom(__DIR__ . '/../../config/core_foundation.php', 'core_foundation');
        $this->mergeConfigFrom(__DIR__ . '/../../config/interceptors.php', 'interceptors');

        include_once __DIR__ . '/../Helpers/helpers.php';
        // TODO feature is incomplete.
        // $this->app->singleton("doc", TestFacadeDoc::class);

        // $this->app->bind('command.test-factory-helper.generate', function ($app) {
        //     return new GenerateFactoryCommand($app['files'], $app['view']);
        // });
        // $this->commands('command.test-factory-helper.generate');


        $this->app->singleton(ServerTimingFacadeService::class, function ($app) {
            return new ServerTimingFacadeService(new \Symfony\Component\Stopwatch\Stopwatch());
        });

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
}
