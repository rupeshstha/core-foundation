<?php

namespace CoreFoundation\Providers;

use CoreFoundation\DevTools\ServerTiming\ServerTimingService;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\Stopwatch\Stopwatch;

class AppMonitorServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(
            __DIR__ . '/../../Config/server-timing.php',
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

    // =========================================================================
    // Octane — reset state between requests
    // =========================================================================

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
        if (! class_exists(\Laravel\Octane\Events\RequestReceived::class)) {
            return;
        }

        $this->app['events']->listen(
            \Laravel\Octane\Events\RequestReceived::class,
            function (\Laravel\Octane\Events\RequestReceived $event): void {
                // Re-resolve from the new request's container scope
                $event->sandbox->make(ServerTimingService::class)->reset();
            },
        );

        // Also reset on task execution so job timings don't bleed into requests
        if (class_exists(\Laravel\Octane\Events\TaskReceived::class)) {
            $this->app['events']->listen(
                \Laravel\Octane\Events\TaskReceived::class,
                function (\Laravel\Octane\Events\TaskReceived $event): void {
                    $event->sandbox->make(ServerTimingService::class)->reset();
                },
            );
        }
    }

    // =========================================================================
    // Publishables
    // =========================================================================

    private function registerPublishables(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->publishes([
            __DIR__ . '/../../Config/server-timing.php' => config_path('server-timing.php'),
        ], 'core-foundation-server-timing');
    }
}
