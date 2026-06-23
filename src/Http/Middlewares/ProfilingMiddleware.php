<?php

namespace CoreFoundation\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\DevTools\ServerTiming\ServerTimingService;

/**
 * ProfilingMiddleware
 *
 * Records controller dispatch time in the Server-Timing header.
 * Works alongside ServerTimingMiddleware — place this AFTER it so bootstrap
 * is measured separately from controller execution.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ PLACEMENT IN MIDDLEWARE STACK                                                │
 * │                                                                             │
 * │   ->withMiddleware(function (Middleware $middleware) {                       │
 * │       $middleware->prepend(ServerTimingMiddleware::class);                   │
 * │       $middleware->append(ProfilingMiddleware::class);                       │
 * │   })                                                                        │
 * │                                                                             │
 * │ Or apply only to specific routes:                                           │
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
 * │ 2. Records full controller dispatch time as a Server-Timing metric          │
 * │ 3. Adds a "profiling-active" label so DevTools shows profiling is running  │
 * │ 4. Records a "slow-Controller" warning metric when threshold is exceeded    │
 * │                                                                             │
 * │ For fine-grained service/repository measurement, use MeasuresPerformance   │
 * │ or MeasuresCachePerformance inside the service class itself.                │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
class ProfilingMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $this->isEnabled()) {
            return $next($request);
        }

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

    private function isEnabled(): bool
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
