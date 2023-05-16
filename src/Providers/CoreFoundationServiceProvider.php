<?php

namespace Rupeshstha\CoreFoundation\Providers;

use Illuminate\Support\ServiceProvider;
use Rupeshstha\CoreFoundation\Contracts\StrategyContract;
use Rupeshstha\CoreFoundation\Services\StrategyService;

class CoreFoundationServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap the application services.
     */
    public function boot()
    {
        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__ . '/../config/config.php' => config_path('core-foundation.php'),
            ], 'config');
        }
    }

    /**
     * Register the application services.
     */
    public function register()
    {
        $this->app->bind(
            abstract: StrategyContract::class,
            concrete: StrategyService::class
        );
    }
}
