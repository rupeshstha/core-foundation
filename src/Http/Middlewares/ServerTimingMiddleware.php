<?php

namespace CoreFoundation\Http\Middlewares;

use Closure;
use CoreFoundation\DevTools\ServerTiming\ServerTimingService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * ServerTimingMiddleware
 *
 * Injects the W3C Server-Timing header into every response when enabled.
 * Must be placed as early as possible in the middleware stack to capture
 * the most accurate bootstrap time.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ SETUP — Laravel 11+ (bootstrap/app.php)                                     │
 * │                                                                             │
 * │   ->withMiddleware(function (Middleware $middleware) {                       │
 * │       $middleware->prepend(ServerTimingMiddleware::class);                   │
 * │   })                                                                        │
 * │                                                                             │
 * │ SETUP — Laravel 10 (app/Http/Kernel.php)                                    │
 * │                                                                             │
 * │   protected $middleware = [                                                 │
 * │       \CoreFoundation\Http\Middlewares\ServerTimingMiddleware::class,        │
 * │       // ...                                                                 │
 * │   ];                                                                        │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ OCTANE                                                                      │
 * │                                                                             │
 * │ Safe to use with Octane. The ServiceProvider binds ServerTimingService as   │
 * │ a SCOPED singleton, so it is re-created on every Octane request. The        │
 * │ middleware re-resolves the service from the container on each request,      │
 * │ so it always gets the fresh scoped instance.                                │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
class ServerTimingMiddleware
{
    /**
     * The microtime at which this request reached the middleware stack.
     * Used to calculate elapsed time from the perspective of this middleware.
     */
    private readonly float $middlewareStart;

    public function __construct()
    {
        $this->middlewareStart = microtime(true);
    }

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isEnabled()) {
            return $next($request);
        }
        // Re-resolve per request — critical for Octane scoped binding correctness.
        /** @var ServerTimingService $timing */
        $timing = app(ServerTimingService::class);

        // Bootstrap = time from LARAVEL_START (or server request time) to
        // the point this middleware first runs. Captures autoloading, booting,
        // service provider registration, and early middleware.
        $timing->record('Bootstrap', $this->getBootstrapDurationMs(), 'Bootstrap');

        // App = time spent inside the application stack (controllers, services, etc.)
        $timing->start('App', 'Application');

        $response = $next($request);

        $timing->stop('App');

        // Flush any developer measurements that were started but never stopped.
        $timing->flush();

        // Total = full wall-clock time from the server's perspective.
        $timing->record('Total', $this->getElapsedMs($this->getRequestStartTime()), 'Total');

        $headerValue = $timing->toHeaderValue();

        if (! empty($headerValue)) {
            $response->headers->set('Server-Timing', $headerValue);
        }

        return $response;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Whether Server-Timing is enabled for the current environment.
     * Reads from config/server-timing.php.
     */
    private function isEnabled(): bool
    {
        if (! config('server-timing.enabled', true)) {
            return false;
        }

        $environments = config('server-timing.environments', []);

        // Empty array = enabled in all environments.
        if (empty($environments)) {
            return true;
        }

        return app()->environment($environments);
    }

    /**
     * The time LARAVEL_START was defined — i.e. the very first line of public/index.php.
     * Falls back to $_SERVER['REQUEST_TIME_FLOAT'] then microtime(true).
     */
    private function getRequestStartTime(): float
    {
        if (defined('LARAVEL_START')) {
            return LARAVEL_START;
        }

        return $_SERVER['REQUEST_TIME_FLOAT'] ?? $this->middlewareStart;
    }

    /**
     * Time elapsed from the given start point to right now, in milliseconds.
     */
    private function getElapsedMs(float $start): float
    {
        return (microtime(true) - $start) * 1000;
    }

    /**
     * Bootstrap duration = from LARAVEL_START to when this middleware first ran.
     * This captures autoloading, booting, and all early middleware overhead.
     */
    private function getBootstrapDurationMs(): float
    {
        return ($this->middlewareStart - $this->getRequestStartTime()) * 1000;
    }
}
