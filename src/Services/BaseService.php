<?php

namespace CoreFoundation\Services;

use CoreFoundation\Traits\HasEvent;
use CoreFoundation\Traits\HasFactory;
use CoreFoundation\Traits\HasPipeline;
use CoreFoundation\Traits\HasCacheable;
use CoreFoundation\Manipulators\ObjectMutable;

/**
 * BaseService
 *
 * Foundation class for all business logic in the application.
 * Composes four independent capabilities via traits — use only what you need.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ CAPABILITIES AT A GLANCE                                                    │
 * │                                                                             │
 * │  HasEvent     → namespaced pub/sub event dispatch (fire-and-forget)         │
 * │  HasPipeline  → before/after execution hooks via Laravel's Pipeline         │
 * │  HasCacheable → tag-based read-through cache with atomic bust               │
 * │  HasFactory   → conditional class preference (Magento-style swap)           │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ PATTERN A — Monolithic service (all logic in one class)                     │
 * │ Best for: simple CRUD domains, small teams, early-stage modules             │
 * │                                                                             │
 * │   class OrderService extends BaseService                                    │
 * │   {                                                                         │
 * │       protected ?string $eventPrefix = 'order';                             │
 * │                                                                             │
 * │       public function place(ObjectMutable $data): ObjectMutable             │
 * │       {                                                                     │
 * │           return $this->throughPipes('place', $data, function ($data) {     │
 * │               $order  = Order::create($data->toArray());                    │
 * │               $result = ObjectMutable::from($order->toArray());             │
 * │                                                                             │
 * │               $this->bustCache(['orders']);                                 │
 * │               $this->dispatch('placed', $result);                           │
 * │                                                                             │
 * │               return $result;                                               │
 * │           });                                                               │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ PATTERN B — Orchestrating service (delegates to single-purpose actions)     │
 * │ Best for: complex domains, multiple consumers, high testability             │
 * │                                                                             │
 * │   class OrderService extends BaseService                                    │
 * │   {                                                                         │
 * │       protected ?string $eventPrefix = 'order';                             │
 * │                                                                             │
 * │       public function __construct(                                          │
 * │           private readonly PlaceOrderAction   $placeOrder,                  │
 * │           private readonly ReserveStockAction $reserveStock,                │
 * │           private readonly NotifyBuyerAction  $notifyBuyer,                 │
 * │       ) {}                                                                  │
 * │                                                                             │
 * │       public function place(ObjectMutable $data): ObjectMutable             │
 * │       {                                                                     │
 * │           return $this->throughPipes('place', $data, function ($data) {     │
 * │               $result = $this->placeOrder->execute($data);                  │
 * │               $this->reserveStock->execute($result);                        │
 * │               $this->notifyBuyer->execute($result);                         │
 * │                                                                             │
 * │               $this->bustCache(['orders']);                                 │
 * │               $this->dispatch('placed', $result);                           │
 * │                                                                             │
 * │               return $result;                                               │
 * │           });                                                               │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ REGISTERING PIPES (in a module ServiceProvider)                             │
 * │                                                                             │
 * │   OrderService::addPipe('place', ValidateInventoryPipe::class);             │
 * │   OrderService::addPipe('place', ApplyDiscountPipe::class);                 │
 * │                                                                             │
 * │ Each pipe receives the payload and a $next closure:                         │
 * │                                                                             │
 * │   class ValidateInventoryPipe                                               │
 * │   {                                                                         │
 * │       public function handle(ObjectMutable $data, Closure $next): mixed     │
 * │       {                                                                     │
 * │           // before                                                         │
 * │           if (! $this->inStock($data->get('product_id'))) {                 │
 * │               throw new OutOfStockException();                              │
 * │           }                                                                 │
 * │                                                                             │
 * │           $result = $next($data); // run next pipe + core logic             │
 * │                                                                             │
 * │           // after                                                          │
 * │           $result->set('inventory_reserved', true);                         │
 * │                                                                             │
 * │           return $result;                                                   │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ PREFERENCE / CLASS SWAP (in a module ServiceProvider)                       │
 * │                                                                             │
 * │   OrderService::setPreference(                                              │
 * │       concrete:  SubscriptionOrderService::class,                           │
 * │       condition: fn () => config('modules.subscriptions.enabled'),          │
 * │   );                                                                        │
 * │                                                                             │
 * │   $this->app->bind(OrderService::class, fn () =>                            │
 * │       (new OrderService)->resolvePreference()                               │
 * │   );                                                                        │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
abstract class BaseService
{
    use HasCacheable;
    use HasEvent;
    use HasFactory;
    use HasPipeline;

    // =========================================================================
    // Container-aware static entry point
    // =========================================================================

    /**
     * Resolve this service through Laravel's container.
     * Constructor dependencies are injected automatically.
     *
     *   OrderService::make()->place($data);
     */
    final public static function make(): static
    {
        return app(static::class);
    }

    // =========================================================================
    // ObjectMutable factory — available to all services
    // =========================================================================

    /**
     * Instantiate an ObjectMutable data carrier.
     * Keeps service methods free of ObjectMutable import statements.
     *
     *   $data  = $this->data($request->validated());  // input carrier
     *   $result = $this->data();                       // empty result carrier
     */
    final protected function data(array $input = []): ObjectMutable
    {
        return ObjectMutable::from($input);
    }
}
