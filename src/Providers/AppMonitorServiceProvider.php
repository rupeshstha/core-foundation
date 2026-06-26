<?php

namespace CoreFoundation\Providers;

use Illuminate\Support\ServiceProvider;
use Laravel\Octane\Events\TaskReceived;
use Laravel\Octane\Events\RequestReceived;
use Symfony\Component\Stopwatch\Stopwatch;
use CoreFoundation\DevTools\ServerTiming\ServerTimingService;

class AppMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__.'/../../config/server-timing.php',
            'server-timing',
        );

        // Scoped singleton — re-created per request in Octane.
        // In standard Laravel it behaves as a normal singleton.
        $this->app->scoped(ServerTimingService::class, function (): ServerTimingService {
            return new ServerTimingService(new Stopwatch(morePrecision: true));
        });

        // Alias so the Facade resolves correctly
        $this->app->alias(ServerTimingService::class, 'server-timing');
    }

    public function boot(): void
    {
        $this->registerPublishables();
        $this->registerOctaneReset();
    }

    /**
     * Register a listener that resets the ServerTimingService before each
     * Octane request. This is a belt-and-suspenders safety measure alongside
     * the scoped binding — it handles edge cases where the scoped container
     * is not fully flushed between requests.
     *
     * Only registers when Octane is installed and the event class exists,
     * so this is a no-op in standard Laravel environments.
     */
    private function registerOctaneReset(): void
    {
        if (! class_exists(RequestReceived::class)) {
            return;
        }

        $this->app['events']->listen(
            RequestReceived::class,
            function (RequestReceived $event): void {
                // Re-resolve from the new request's container scope
                $event->sandbox->make(ServerTimingService::class)->reset();
            },
        );

        // Also reset on task execution so job timings don't bleed into requests
        if (class_exists(TaskReceived::class)) {
            $this->app['events']->listen(
                TaskReceived::class,
                function (TaskReceived $event): void {
                    $event->sandbox->make(ServerTimingService::class)->reset();
                },
            );
        }
    }

    private function registerPublishables(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__.'/../../config/server-timing.php' => config_path('server-timing.php'),
        ], 'core-foundation-server-timing');
    }
}
