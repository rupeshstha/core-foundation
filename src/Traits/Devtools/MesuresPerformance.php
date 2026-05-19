<?php

namespace CoreFoundation\Traits\Devtools;

use CoreFoundation\Facades\ServerTiming;

/**
 * MeasuresPerformance
 *
 * Optional auto-instrumentation trait for BaseService.
 * When used, wraps `throughPipes()` in a Server-Timing measurement
 * automatically — no manual start/stop calls needed in service methods.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE — opt-in per service                                                  │
 * │                                                                             │
 * │   class OrderService extends BaseService                                    │
 * │   {                                                                         │
 * │       use MeasuresPerformance;                                              │
 * │                                                                             │
 * │       public function place(array $validated): PlaceOrderData               │
 * │       {                                                                     │
 * │           // throughPipes() is automatically measured as                    │
 * │           // "OrderService.place" in Server-Timing headers.                 │
 * │           return $this->throughPipes('place', $validated, function ($data) {│
 * │               return PlaceOrderData::fromArray(Order::create($data)->toArray());
 * │           });                                                               │
 * │       }                                                                     │
 * │   }                                                                         │
 * │                                                                             │
 * │ You can also measure arbitrary blocks:                                      │
 * │                                                                             │
 * │   $result = $this->measure('expensive-op', fn () => $this->doHeavyWork()); │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHEN NOT TO USE                                                             │
 * │                                                                             │
 * │ Do not add this trait if Server-Timing is not registered in the app.        │
 * │ The trait silently no-ops when the Facade resolves to null (i.e. when       │
 * │ ServerTimingService is not bound), so it is safe to leave it on in          │
 * │ production — no errors, just no header.                                     │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait MeasuresPerformance
{
    /**
     * Override throughPipes() to wrap the pipeline call in a Server-Timing measurement.
     *
     * The metric name is "{ClassName}.{hook}" e.g. "OrderService.place".
     * Falls through to the parent implementation unchanged if Server-Timing is disabled.
     */
    final protected function throughPipes(string $hook, mixed $payload, \Closure $core): mixed
    {
        $metricName  = class_basename(static::class) . '.' . $hook;
        $description = static::class . '::' . $hook;

        return ServerTiming::wrap($metricName, fn () => parent::throughPipes($hook, $payload, $core), $description);
    }

    /**
     * Measure any callable block and record it under the given metric name.
     * Convenience wrapper around ServerTiming::wrap() for use inside service methods.
     *
     * @template T
     * @param  callable(): T  $callable
     * @return T
     */
    final protected function measure(string $name, callable $callable, ?string $description = null): mixed
    {
        return ServerTiming::wrap($name, $callable, $description);
    }
}
