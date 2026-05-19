<?php

namespace CoreFoundation\Traits;

use Illuminate\Support\Facades\Event;

/**
 * HasEvent
 *
 * Namespaced, pub/sub event dispatch for service classes.
 * Wraps Laravel's Event facade with a domain prefix convention so events
 * from different services never collide and are easy to trace in logs.
 *
 * This trait is ONLY for pub/sub notification (fire-and-forget).
 * For before/after execution hooks that modify data, see HasPipeline.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ SETUP                                                                       │
 * │                                                                             │
 * │   class OrderService extends BaseService                                    │
 * │   {                                                                         │
 * │       protected ?string $eventPrefix = 'order';                             │
 * │   }                                                                         │
 * │                                                                             │
 * │ DISPATCH                                                                    │
 * │                                                                             │
 * │   $this->dispatch('placed', $result);         // fires: 'order.placed'      │
 * │   $this->dispatch('cancelled', $result);      // fires: 'order.cancelled'   │
 * │   $this->dispatch('system.alert', $d, false); // fires: 'system.alert'      │
 * │                                               // (prefix bypassed)          │
 * │                                                                             │
 * │ SUPPRESS (bulk operations, imports, seeding):                               │
 * │                                                                             │
 * │   $this->withoutEvents(function () {                                        │
 * │       foreach ($orders as $order) {                                         │
 * │           $this->place($order); // no events fired                          │
 * │       }                                                                     │
 * │   });                                                                       │
 * │                                                                             │
 * │ LISTEN (in EventServiceProvider):                                           │
 * │                                                                             │
 * │   Event::listen('order.placed', SendOrderConfirmation::class);              │
 * │   Event::listen('order.*',      AuditOrderEvents::class);                   │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait HasEvent
{
    /**
     * Internal dispatch switch. Never set directly — use withoutEvents().
     */
    private bool $eventsEnabled = true;

    /**
     * Namespace prefix for all events dispatched by this service.
     * Snake_case, domain-scoped. e.g. 'order', 'user', 'payment'.
     * All dispatched events become: '{prefix}.{event}'
     */
    protected ?string $eventPrefix = null;

    // =========================================================================
    // Dispatch
    // =========================================================================

    /**
     * Dispatch a namespaced event via Laravel's Event system.
     *
     * @param  string  $event  Short event name e.g. 'placed', 'cancelled'
     * @param  mixed  $payload  Passed to all listeners
     * @param  bool  $prefixed  False to bypass the domain prefix for this call
     */
    final protected function dispatch(string $event, mixed $payload = [], bool $prefixed = true): void
    {
        if (! $this->eventsEnabled) {
            return;
        }

        $key = ($this->eventPrefix && $prefixed)
            ? "{$this->eventPrefix}.{$event}"
            : $event;

        Event::dispatch($key, $payload);
    }

    /**
     * Execute a callback with all event dispatch suppressed.
     * Dispatch state is always restored after, even on exception.
     *
     * @param  callable(): mixed  $callback
     */
    final public function withoutEvents(callable $callback): mixed
    {
        $previous = $this->eventsEnabled;
        $this->eventsEnabled = false;

        try {
            return $callback();
        } finally {
            $this->eventsEnabled = $previous;
        }
    }
}
