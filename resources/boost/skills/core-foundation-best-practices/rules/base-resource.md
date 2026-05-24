# BaseResource & BaseCollection Rules

## Override `fields()`, Never `toArray()`

`toArray()` is `final` — it enforces the field pipeline. Override `fields()` to define the base field map.

Incorrect:
```php
public function toArray(Request $request): array
{
    return ['id' => $this->id];
}
```

Correct:
```php
protected function fields(Request $request): array
{
    return [
        'id'     => $this->resource->id,
        'status' => $this->resource->status,
        'total'  => $this->resource->total,
        'user'   => new UserResource($this->whenLoaded('user')),
    ];
}
```

## Timestamps — Opt In Inside `fields()`

```php
protected function fields(Request $request): array
{
    return $this->withTimestamps([
        'id'    => $this->resource->id,
        'total' => $this->resource->total,
    ]);
}
```

`withTimestamps()` appends `created_at` and `updated_at` in ISO 8601 format.

## Modular Extension — Always from ServiceProvider

Module B never edits Module A's resource source. All field changes are registered from a ServiceProvider.

Incorrect (editing another module's resource):
```php
// Inside OrderResource::fields():
'subscription_plan' => $this->resource->subscription?->plan_code, // wrong
```

Correct (from SubscriptionServiceProvider):
```php
protected function extendResources(): void
{
    $this->resource(OrderResource::class)
        ->field('subscription_plan', fn ($order, $req) => $order->subscription?->plan_code)
        ->remove('internal_cost');
}
```

## Field Pipeline Order

1. `fields()` — base definition
2. `addField()` additions / overrides — same key replaces base value
3. `removeField()` exclusions — always wins, applied last

An `addField()` with the same key as a `fields()` key replaces the base value. `removeField()` wins over both.

## BaseCollection — Always Declare `$collects`

No naming-convention magic. Always explicit. Missing `$collects` throws `LogicException` at construction time.

Incorrect:
```php
final class OrderCollection extends BaseCollection
{
    // $collects not declared — throws LogicException
}
```

Correct:
```php
final class OrderCollection extends BaseCollection
{
    public string $collects = OrderResource::class;
}
```

## `$wrap = null` — The Envelope Belongs to the Controller

Both `BaseResource` and `BaseCollection` have `$wrap = null`. Never change this. The envelope wrapping is `BaseController`'s responsibility.

## Conditional Fields

Laravel's `$this->when()`, `$this->whenLoaded()`, `$this->mergeWhen()` all work inside `fields()`:

```php
protected function fields(Request $request): array
{
    return [
        'id'      => $this->resource->id,
        'user'    => new UserResource($this->whenLoaded('user')),
        'summary' => $this->when($request->has('summary'), fn () => $this->buildSummary()),
    ];
}
```

## Using in Controllers

```php
// Single resource
return $this->successResponse('Order retrieved.', new OrderResource($result));

// Paginated collection
return $this->paginatedResponse('Orders fetched.', new OrderCollection($paginator));

// Non-paginated collection
return $this->successResponse('Orders fetched.', new OrderCollection($orders));
```
