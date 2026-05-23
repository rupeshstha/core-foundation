<?php

namespace CoreFoundation\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\DevTools\ServerTiming\ServerTimingService;

/**
 * ProfilingMiddleware
 *
 * Activates the full profiling layer for a request.
 * Works alongside ServerTimingMiddleware — this middleware enables
 * per-layer profiling flags that #[Profile]-marked methods check.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ PLACEMENT IN MIDDLEWARE STACK                                                │
 * │                                                                             │
 * │ Place AFTER ServerTimingMiddleware (which measures bootstrap):               │
 * │                                                                             │
 * │   ->withMiddleware(function (Middleware $middleware) {                       │
 * │       $middleware->prepend(ServerTimingMiddleware::class);                   │
 * │       $middleware->append(ProfilingMiddleware::class);                       │
 * │   })                                                                        │
 * │                                                                             │
 * │ Or apply only to specific routes via route middleware:                      │
 * │                                                                             │
 * │   Route::middleware(['profiling'])->group(function () {                     │
 * │       Route::get('/orders', [OrderController::class, 'index']);             │
 * │   });                                                                       │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHAT THIS MIDDLEWARE DOES                                                   │
 * │                                                                             │
 * │ 1. Checks profiling.enabled and environment gates                           │
 * │ 2. Records the Controller dispatch time as a Server-Timing metric           │
 * │ 3. Sets a request attribute that HasProfilable checks to decide             │
 * │    whether to wrap #[Profile]-marked methods                                │
 * │ 4. Adds a "profiling-active" label to the Server-Timing header so          │
 * │    DevTools shows that profiling was running for this request              │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHAT IT DOES NOT DO                                                         │
 * │                                                                             │
 * │ It does NOT use debug_backtrace() or AOP to intercept every method call.   │
 * │ PHP does not support AOP natively. Intercepting all calls would require     │
 * │ xdebug (not for production), a proxy pattern (complex), or debug_backtrace  │
 * │ (fragile, expensive). None of these are acceptable.                         │
 * │                                                                             │
 * │ Instead: developers mark methods they care about with #[Profile].          │
 * │ Selective explicit profiling > carpet-bombing every method call.            │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
class ProfilingMiddleware
{
    /**
     * Request attribute key — set to true when profiling is active.
     * HasProfilable reads this via request() helper to decide whether to measure.
     */
    public const ACTIVE_KEY = 'core_foundation.profiling.active';

    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isEnabled($request)) {
            return $next($request);
        }

        // Mark request as profiling-active — HasProfilable reads this
        $request->attributes->set(self::ACTIVE_KEY, true);

        /** @var ServerTimingService $timing */
        $timing = app(ServerTimingService::class);

        // Label the profiling session in the header so DevTools shows it
        $timing->label('profiling', 'active');

        // Measure the full controller dispatch
        $timing->start('Controller', 'Controller dispatch');
        $response = $next($request);
        $timing->stop('Controller');

        // Slow controller warning
        $threshold = config('profiling.thresholds.controller');
        if ($threshold !== null) {
            $duration = $timing->all()['Controller'] ?? null;
            if ($duration !== null && $duration >= $threshold) {
                $timing->record(
                    name: 'slow-Controller',
                    durationMs: $duration,
                    description: "Slow controller: exceeded {$threshold}ms",
                );
            }
        }

        return $response;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    private function isEnabled(Request $request): bool
    {
        if (! config('profiling.enabled', false)) {
            return false;
        }

        $environments = config('profiling.environments', []);

        if (! empty($environments) && ! app()->environment($environments)) {
            return false;
        }

        if (! config('profiling.layers.controllers', true)) {
            return false;
        }

        return true;
    }
}
