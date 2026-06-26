<?php

namespace CoreFoundation\Services;

use CoreFoundation\Traits\HasFactory;
use CoreFoundation\Traits\HasPipeline;
use CoreFoundation\Traits\HasDeferrable;
use CoreFoundation\Traits\HasServiceCache;
use CoreFoundation\Manipulators\BaseDataObject;

/**
 * BaseService
 *
 * Foundation class for all business logic in the application.
 * Composes four independent capabilities via traits — use only what you need.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ CAPABILITIES AT A GLANCE                                                    │
 * │                                                                             │
 * │  HasPipeline  → before/after execution hooks via Laravel's Pipeline         │
 * │  HasServiceCache → dependency-aware cache (wraps HasCacheable)              │
 * │  HasFactory   → conditional class preference (Magento-style swap)           │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ PATTERN A — Monolithic service (all logic in one class)                     │
 * │ Best for: simple CRUD domains, small teams, early-stage modules             │
 * │                                                                             │
 * │   class OrderService extends BaseService                                    │
 * │   {                                                                         │
 * │       public function place(BaseDataObject $data): BaseDataObject             │
 * │       {                                                                     │
 * │           return $this->throughPipes('place', $data, function ($data) {     │
 * │               $order  = Order::create($data->toArray());                    │
 * │               $result = BaseDataObject::from($order->toArray());             │
 * │                                                                             │
 * │               $this->bustCache(['orders']);                                 │
 * │               OrderPlaced::dispatch($result);                               │
 * │                                                                             │
 * │               return $result;                                               │
 * │           });                                                               │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ OPT-IN PERFORMANCE MEASUREMENT                                              │
 * │                                                                             │
 * │ Add measurement traits for production observability via Server-Timing       │
 * │ headers. Measurements appear in browser DevTools Network tab.               │
 * │                                                                             │
 * │   use CoreFoundation\Traits\Devtools\MeasuresPerformance;                  │
 * │   use CoreFoundation\Traits\Devtools\MeasuresCachePerformance;             │
 * │                                                                             │
 * │   class OrderService extends BaseService                                    │
 * │   {                                                                         │
 * │       use MeasuresPerformance;        // auto-measure throughPipes()       │
 * │       use MeasuresCachePerformance;   // measure cache hit/miss timing     │
 * │                                                                             │
 * │       public function place(array $data): BaseDataObject                     │
 * │       {                                                                     │
 * │           // Automatically appears as "OrderService.place" in               │
 * │           // Server-Timing headers with duration                            │
 * │           return $this->throughPipes('place', $data, function ($data) {     │
 * │               return $this->data(Order::create($data)->toArray());          │
 * │           });                                                               │
 * │       }                                                                     │
 * │                                                                             │
 * │       public function measure(string $name, callable $callback): mixed     │
 * │       {                                                                     │
 * │           // Manual measurement for arbitrary blocks                        │
 * │           return $this->measure('expensive-op', $callback);                 │
 * │       }                                                                     │
 * │   }                                                                         │
 * │                                                                             │
 * │ Safe to use in production — no-ops when ServerTiming is disabled.          │
 * └─────────────────────────────────────────────────────────────────────────────┘
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ PATTERN B — Orchestrating service (delegates to single-purpose actions)     │
 * │ Best for: complex domains, multiple consumers, high testability             │
 * │                                                                             │
 * │   class OrderService extends BaseService                                    │
 * │   {                                                                         │
 * │       public function __construct(                                          │
 * │           private readonly PlaceOrderAction   $placeOrder,                  │
 * │           private readonly ReserveStockAction $reserveStock,                │
 * │           private readonly NotifyBuyerAction  $notifyBuyer,                 │
 * │       ) {}                                                                  │
 * │                                                                             │
 * │       public function place(BaseDataObject $data): BaseDataObject             │
 * │       {                                                                     │
 * │           return $this->throughPipes('place', $data, function ($data) {     │
 * │               $result = $this->placeOrder->execute($data);                  │
 * │               $this->reserveStock->execute($result);                        │
 * │               $this->notifyBuyer->execute($result);                         │
 * │                                                                             │
 * │               $this->bustCache(['orders']);                                 │
 * │               OrderPlaced::dispatch($result);                               │
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
 * │       public function handle(BaseDataObject $data, Closure $next): mixed     │
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
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ DEFERRABLE PATTERN IN SERVICES                                              │
 *
 * The standard write method pattern with defer:
 *
 *   public function place(array $validated): PlaceOrderData
 *   {
 *       return $this->throughPipes('place', $validated, function (array $data): PlaceOrderData {
 *           // 1. Core write — must complete before response
 *           $order  = Order::create($data);
 *           $result = PlaceOrderData::fromArray($order->toArray());
 *
 *           // 2. Cache bust — deferred, user doesn't wait
 *           $this->deferCacheBust(['orders']);
 *
 *           // 3. Events — immediate (listeners may have return-path deps)
 *           OrderPlaced::dispatch($result);
 *
 *           // 4. Non-critical side effects — deferred
 *           $this->defer(fn () => AuditLog::record('order.placed', $order->id), 'order.audit');
 *
 *           return $result;
 *       });
 *   }
 * │                                                                             │
 * │ DECISION GUIDE: defer() vs dispatch() vs queue()                            │
 * │                                                                             │
 * │   defer()               Post-response, same process, no retry              │
 * │                         Use for: cache busting, audit logs, analytics      │
 * │                                                                             │
 * │   Event::dispatch()     Immediate, same request, listeners block response   │
 * │                         Use for: events where listeners affect the response │
 * │                                                                             │
 * │   dispatch()->onQueue() Async, separate worker, retry, persistent          │
 * │                         Use for: email, heavy processing, critical work    │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
abstract class BaseService
{
    use HasDeferrable;
    use HasFactory;
    use HasPipeline;
    use HasServiceCache;

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

    /**
     * Instantiate an BaseDataObject data carrier.
     * Keeps service methods free of BaseDataObject import statements.
     *
     *   $data  = $this->data($request->validated());  // input carrier
     *   $result = $this->data();                       // empty result carrier
     */
    final protected function data(array $input = []): BaseDataObject
    {
        return BaseDataObject::fromArray($input);
    }
}
