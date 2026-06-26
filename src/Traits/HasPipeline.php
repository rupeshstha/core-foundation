<?php

namespace CoreFoundation\Traits;

use Closure;
use Illuminate\Pipeline\Pipeline;

/**
 * HasPipeline
 *
 * Before/after/around execution hooks for service methods using Laravel's Pipeline.
 *
 * This replaces the debug_backtrace() interceptor pattern with an explicit,
 * testable, Laravel-idiomatic equivalent. The developer names the hook point
 * and passes the payload — no runtime magic, no frame counting.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ CONCEPT                                                                     │
 * │                                                                             │
 * │ A "pipe" is any class with a handle(mixed $payload, Closure $next) method.  │
 * │ Pipes registered against a hook point are executed in order before the      │
 * │ core logic runs, and optionally after (via the $next closure).              │
 * │                                                                             │
 * │ This is exactly how Laravel's HTTP middleware works — because it IS         │
 * │ Laravel's middleware system, applied to service methods.                    │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ REGISTERING PIPES (in a ServiceProvider)                                    │
 * │                                                                             │
 * │ Register pipes against a named hook point on a specific service:            │
 * │                                                                             │
 * │   OrderService::addPipe('place', ValidateInventoryPipe::class);             │
 * │   OrderService::addPipe('place', ApplyDiscountPipe::class);                 │
 * │   OrderService::addPipe('place', NotifyWarehousePipe::class);               │
 * │                                                                             │
 * │ Pipes run in the order they are registered.                                 │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WRITING A PIPE                                                              │
 * │                                                                             │
 * │ A pipe is any class with a handle() method. It receives the payload and     │
 * │ a $next closure. Call $next($payload) to pass control to the next pipe.     │
 * │ Returning without calling $next short-circuits the pipeline.                │
 * │                                                                             │
 * │   class ValidateInventoryPipe                                               │
 * │   {                                                                         │
 * │       public function handle(BaseDataObject $data, Closure $next): mixed     │
 * │       {                                                                     │
 * │           // Before: validate before core logic runs                        │
 * │           if (! $this->inventoryAvailable($data->get('product_id'))) {      │
 * │               throw new InsufficientInventoryException();                   │
 * │           }                                                                 │
 * │                                                                             │
 * │           // Run the next pipe (and eventually the core logic)              │
 * │           $result = $next($data);                                           │
 * │                                                                             │
 * │           // After: modify or inspect the result                            │
 * │           $result->set('inventory_reserved', true);                         │
 * │                                                                             │
 * │           return $result;                                                   │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USING PIPES IN A SERVICE METHOD                                             │
 * │                                                                             │
 * │   class OrderService extends BaseService                                    │
 * │   {                                                                         │
 * │       public function place(BaseDataObject $data): BaseDataObject             │
 * │       {                                                                     │
 * │           return $this->throughPipes(                                       │
 * │               hook:    'place',                                             │
 * │               payload: $data,                                               │
 * │               core:    function (BaseDataObject $data): BaseDataObject {      │
 * │                   $order = Order::create($data->toArray());                 │
 * │                   return BaseDataObject::from($order->toArray());            │
 * │               },                                                            │
 * │           );                                                                │
 * │       }                                                                     │
 * │   }                                                                         │
 * │                                                                             │
 * │ The pipeline runs: pipes → core logic → pipes (post, in reverse order).    │
 * │ No registered pipes? The core closure runs directly — zero overhead.        │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait HasPipeline
{
    /**
     * Pipe registry: [ ServiceClass::class => [ hookName => [PipeClass, ...] ] ]
     */
    protected static array $pipes = [];

    /**
     * Register a pipe class against a named hook point on this service.
     *
     * @param  string  $hook  The hook name matching the one used in throughPipes()
     * @param  string  $pipe  FQCN of the pipe class — must have a handle() method
     */
    public static function addPipe(string $hook, string $pipe): void
    {
        static::$pipes[static::class][$hook][] = $pipe;
    }

    /**
     * Remove all registered pipes for a hook on this service.
     * Useful for testing to reset between cases.
     */
    public static function clearPipes(string $hook): void
    {
        unset(static::$pipes[static::class][$hook]);
    }

    /**
     * Remove all registered pipes for this service entirely.
     */
    public static function clearAllPipes(): void
    {
        unset(static::$pipes[static::class]);
    }

    /**
     * Run the payload through all registered pipes for the given hook,
     * with the core logic as the final destination.
     *
     * If no pipes are registered for the hook, the core closure runs directly.
     *
     * @param  string  $hook  Named hook point matching addPipe() registrations
     * @param  mixed  $payload  Data passed through the pipeline
     * @param  Closure  $core  The service's core business logic
     */
    final protected function throughPipes(string $hook, mixed $payload, Closure $core): mixed
    {
        $pipes = static::$pipes[static::class][$hook] ?? [];

        if (empty($pipes)) {
            return $core($payload);
        }

        return app(Pipeline::class)
            ->send($payload)
            ->through($pipes)
            ->then($core);
    }

    /**
     * Return the pipes registered for a given hook on this service.
     * Useful for debugging and testing.
     *
     * @return array<string>
     */
    final public function getPipes(string $hook): array
    {
        return static::$pipes[static::class][$hook] ?? [];
    }
}
