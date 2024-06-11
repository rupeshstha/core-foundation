<?php

namespace CoreFoundation\Providers;

use Illuminate\Support\ServiceProvider;
use CoreFoundation\Contracts\StrategyContract;
use CoreFoundation\Services\StrategyService;

class CoreFoundationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../../config/core_foundation.php' => config_path('core_foundation.php'),
            ], 'config');
        }
    }

    /**
     * Register the application services.
     */
    public function register(): void
    {
        $this->app->register(AppMonitorServiceProvider::class);
        $this->bindServices();

        $this->mergeConfigFrom(__DIR__ . '/../../config/core_foundation.php', 'core_foundation');
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
