# BaseService Rules

## Return Types — Always `BaseDataObject`

A service method must return a `BaseDataObject` subclass. Never return `JsonResponse`, a raw Eloquent model, or a plain array.

Incorrect:
```php
public function place(array $validated): array
{
    return Order::create($validated)->toArray();
}
```

Correct:
```php
public function place(array $validated): PlaceOrderData
{
    return $this->throughPipes('place', $validated, function (array $data): PlaceOrderData {
        $order = Order::create($data);
        return PlaceOrderData::fromArray($order->toArray());
    });
}
```

## Events — Class-Based, Never String-Keyed

Use Laravel's class-based events. Never dispatch string-keyed events.

Incorrect:
```php
Event::dispatch('order.placed', $data);
```

Correct:
```php
OrderPlaced::dispatch($result);
```

If listeners don't affect the response, defer the dispatch post-response:
```php
$this->defer(fn () => OrderPlaced::dispatch($result), 'event.order.placed');
```

## `HasPipeline` — Execution Hooks Only

`HasPipeline` = before/after hooks that can modify the payload. Use for validation, enrichment, transformation.

NEVER use events to hook execution flow — that is `HasPipeline`'s job.

Correct (using HasPipeline for data modification):
```php
return $this->throughPipes('place', $data, fn ($d) => /* core logic */);
// ValidateInventoryPipe runs before and can modify $data
```

## Pipes — Register From ServiceProvider Only

Never register pipes inside the service class itself.

Incorrect:
```php
class OrderService extends BaseService
{
    public function __construct()
    {
        static::addPipe('place', ValidateInventoryPipe::class); // wrong
    }
}
```

Correct (in ServiceProvider):
```php
protected function extendServices(): void
{
    $this->service(OrderService::class)
        ->pipe('place', ValidateInventoryPipe::class)
        ->pipe('place', ApplyDiscountPipe::class);
}
```

## Pipe Structure

Each pipe receives the payload and a `$next` closure. Return `$next($data)` to continue the chain.

```php
final class ValidateInventoryPipe
{
    public function handle(array $data, Closure $next): mixed
    {
        if (! $this->inStock($data['product_id'])) {
            throw new OutOfStockException;
        }

        $result = $next($data); // run remaining pipes + core logic

        $result['inventory_reserved'] = true; // can modify after too

        return $result;
    }
}
```

## `defer()` vs `dispatch()` vs Queue

| Method | Timing | Retries | Use for |
|---|---|---|---|
| `$this->defer($fn)` | After HTTP response, same process | None | Cache busting, audit logs, analytics |
| `Event::dispatch()` / `SomeEvent::dispatch()` | Immediate, same request | N/A | Listeners that affect the response |
| `Job::dispatch()->onQueue()` | Async worker | Yes | Email, critical processing |

```php
public function place(array $validated): PlaceOrderData
{
    return $this->throughPipes('place', $validated, function (array $data): PlaceOrderData {
        $order  = Order::create($data);
        $result = PlaceOrderData::fromArray($order->toArray());

        $this->deferCacheBust(['orders']);                                    // non-critical
        OrderPlaced::dispatch($result);                                       // immediate pub/sub
        $this->defer(fn () => AuditLog::record($order->id), 'order.audit'); // non-critical

        return $result;
    });
}
```

## `static::class` in Static Registries

All static registries key by `static::class` — never `self::class`. Using `self::class` causes subclass collision when a child service overrides the parent's registry.

Incorrect:
```php
self::$pipes[self::class]['place'][] = $pipe;
```

Correct:
```php
static::$pipes[static::class]['place'][] = $pipe;
```

## Container Entry Point

Prefer constructor injection. Use `::make()` only when outside the container context:

```php
// Preferred (constructor injection via Laravel container):
public function __construct(private readonly OrderService $service) {}

// From outside the container:
OrderService::make()->place($data);
```

## Do Not Use Context Facade Directly

Use `ApplicationContext` subclasses to access request-scoped state — never the `Context` facade directly.

Incorrect:
```php
$tenantId = Context::get('tenant_id');
```

Correct:
```php
$tenantId = ApplicationContext::tenantId();
```
