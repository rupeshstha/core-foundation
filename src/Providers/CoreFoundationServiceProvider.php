<?php

namespace Rupeshstha\CoreFoundation\Providers;

use Illuminate\Support\ServiceProvider;

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
    }
}
