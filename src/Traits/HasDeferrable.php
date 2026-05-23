<?php

namespace CoreFoundation\Traits;

use Illuminate\Support\Defer\DeferredCallback;
use Illuminate\Support\Defer\DeferredCallbackCollection;

/**
 * HasDeferrable
 *
 * Provides structured defer() integration for BaseService.
 * Wraps Laravel's native defer() helper with conventions and named callbacks
 * so deferred operations in services are consistent, cancellable, and documented.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHAT defer() DOES                                                           │
 * │                                                                             │
 * │ The deferred callback executes AFTER the HTTP response has been sent to    │
 * │ the client. The user never waits for it. The PHP process continues after   │
 * │ the response flush and runs all registered deferred callbacks.             │
 * │                                                                             │
 * │ This is NOT the same as a queued job:                                       │
 * │   - No queue driver needed                                                  │
 * │   - Same PHP process (same memory, same DB connection)                     │
 * │   - Runs in milliseconds, not seconds/minutes                               │
 * │   - No retry on failure                                                     │
 * │   - No persistence — if the process dies mid-defer, it's lost             │
 * │                                                                             │
 * │ Use defer() for: cache busting, audit logging, analytics, non-critical     │
 * │ side effects the user doesn't need to wait for.                            │
 * │                                                                             │
 * │ Use a queued job for: email sending, heavy processing, anything that       │
 * │ needs retry, persistence, or runs longer than ~500ms.                      │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ OCTANE                                                                      │
 * │                                                                             │
 * │ defer() works with Octane. Octane calls $app->terminate() after each       │
 * │ request, which flushes all deferred callbacks. No extra configuration.     │
 * │                                                                             │
 * │ Important: deferred callbacks share the same PHP process as the request.   │
 * │ If you have a DB transaction open during the request, it may still be      │
 * │ open when the deferred callback runs. Always ensure transactions are       │
 * │ committed before deferring work that depends on that data being persisted. │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE                                                                       │
 * │                                                                             │
 * │   class OrderService extends BaseService                                    │
 * │   {                                                                         │
 * │       use HasDeferrable;                                                    │
 * │                                                                             │
 * │       public function place(array $validated): PlaceOrderData               │
 * │       {                                                                     │
 * │           $order = Order::create($validated);                               │
 * │           $result = PlaceOrderData::fromArray($order->toArray());           │
 * │                                                                             │
 * │           // Cache bust happens after response — user doesn't wait         │
 * │           $this->deferCacheBust(['orders']);                                 │
 * │                                                                             │
 * │           // Audit log after response — non-blocking                        │
 * │           $this->defer(function () use ($order) {                           │
 * │               AuditLog::create([...]);                                      │
 * │           }, name: 'order.audit');                                          │
 * │                                                                             │
 * │           // Events dispatch immediately — they may have return-path deps  │
 * │           $this->dispatch('placed', $result);                               │
 * │                                                                             │
 * │           return $result;                                                   │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHAT SHOULD AND SHOULD NOT BE DEFERRED                                     │
 * │                                                                             │
 * │ DEFER ✓                                                                    │
 * │   Cache tag flushing after writes                                           │
 * │   Audit log writes                                                          │
 * │   Analytics/metrics recording                                               │
 * │   Sending non-critical notifications (Slack alerts, internal emails)        │
 * │   Updating search index records                                             │
 * │   Warming related caches after a write                                      │
 * │                                                                             │
 * │ DO NOT DEFER ✗                                                              │
 * │   Anything the caller's response body depends on                           │
 * │   Operations inside an open DB transaction                                  │
 * │   Events that trigger return-path listeners (use dispatch() for these)     │
 * │   Critical notifications (use queued jobs for retry guarantee)             │
 * │   Anything that must run even if the process crashes (use jobs)            │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait HasDeferrable
{
    // =========================================================================
    // Core defer helper
    // =========================================================================

    /**
     * Register a callback to run after the HTTP response is sent.
     *
     * Wraps Laravel's defer() helper with optional naming for cancellability.
     * Named callbacks can be cancelled before execution with cancelDefer().
     *
     * @param  callable  $callback  The work to run post-response
     * @param  string|null  $name  Optional name — allows cancellation via cancelDefer()
     * @param  bool  $always  Run even on failed requests/jobs (default: false)
     */
    final protected function defer(
        callable $callback,
        ?string $name = null,
        bool $always = false,
    ): DeferredCallback {
        $deferred = defer($callback, $name, $always);

        return $deferred;
    }

    /**
     * Cancel a previously registered named deferred callback.
     * Has no effect if the named callback was never registered or already ran.
     *
     * @param  string  $name  The name passed to defer() when registering
     */
    final protected function cancelDefer(string $name): void
    {
        DeferredCallbackCollection::forget($name);
    }

    // =========================================================================
    // Structured deferred operations — pre-built for common service patterns
    // =========================================================================

    /**
     * Flush cache tags after the response is sent.
     *
     * Use instead of $this->bustCache() when the cache flush does not need
     * to happen before the response is returned. This is the common case for
     * write operations — the data is committed, the cache just needs busting
     * before the next request reads stale data.
     *
     * IMPORTANT: Do not use this if the current request is inside a DB transaction
     * that has not yet been committed. The cache bust will run after the response,
     * but if the transaction rolls back after the response is sent, the cache will
     * be busted for data that was never persisted.
     *
     * @param  array<string>  $tags  Cache tags to flush
     * @param  string|null  $name  Override the deferred callback name (for cancellability)
     */
    final protected function deferCacheBust(
        array $tags,
        ?string $name = null,
    ): DeferredCallback {
        $callbackName = $name ?? 'cache.bust.'.implode('.', $tags);

        return $this->defer(
            callback: function () use ($tags) {
                $this->bustCache($tags);
            },
            name: $callbackName,
        );
    }

    /**
     * Defer an event dispatch post-response.
     *
     * Use ONLY when the event's listeners have no return-path dependency
     * (i.e. the response does not depend on anything the listener does).
     *
     * For events where listeners must complete before the response is built
     * (e.g. listeners that write to a DB that the response reads), use
     * $this->dispatch() directly — not this method.
     *
     * @param  string  $event  Event key (will be prefixed by eventPrefix if set)
     * @param  mixed  $payload  Event payload
     */
    final protected function deferDispatch(
        string $event,
        mixed $payload = [],
    ): DeferredCallback {
        return $this->defer(
            callback: function () use ($event, $payload) {
                $this->dispatch($event, $payload);
            },
            name: 'event.'.$event,
        );
    }

    /**
     * Defer an arbitrary named callback to always run — even on failed requests.
     *
     * Use for cleanup operations that must run regardless of request success:
     * releasing locks, closing external connections, recording failed attempt logs.
     *
     * @param  string  $name  Required — always() callbacks should be named for clarity
     */
    final protected function deferAlways(callable $callback, string $name): DeferredCallback
    {
        return $this->defer(
            callback: $callback,
            name: $name,
            always: true,
        );
    }
}
