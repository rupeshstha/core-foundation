# BaseService Best Practices

## Always Return `BaseDataObject` — Never `JsonResponse`

Services contain business logic. They must not know about HTTP. Returning `JsonResponse` from a service couples the business layer to the transport layer.

Incorrect:
```php
public function create(array $data): JsonResponse
{
    $order = Order::create($data);
    return response()->json(['id' => $order->id]);
}
```

Correct:
```php
public function create(array $data): OrderData
{
    $order = Order::create($data);
    return OrderData::fromArray($order->toArray());
}
```

## Use `HasPipeline` for Execution Hooks — Never Events

Pipes can modify the payload before and after the core. Events cannot. Use pipes when the result must change; use events for fire-and-forget side effects.

Incorrect (using events for flow control):
```php
public function create(array $data): OrderData
{
    event(new BeforeOrderCreated($data));  // can't modify $data
    $order = Order::create($data);
    return OrderData::fromArray($order->toArray());
}
```

Correct:
```php
public function create(array $data): OrderData
{
    return $this->throughPipes('create', $data, function (array $validated): OrderData {
        $order = Order::create($validated);
        $result = OrderData::fromArray($order->toArray());

        OrderCreated::dispatch($result);  // fire-and-forget pub/sub

        return $result;
    });
}

// Register pipes from a ServiceProvider, never here:
// OrderService::addPipe('create', ValidateInventoryPipe::class);
```

## Use Class-Based Events — Never String-Keyed

String-keyed events have no type safety and cannot be discovered by static analysis.

Incorrect:
```php
Event::dispatch('order.created', $order);
```

Correct:
```php
OrderCreated::dispatch($result);
```

## Caching a Service Result — `HasServiceCache`, Not a New Cache Class

A service sometimes needs to cache something a repository can't: a result computed from several models, an external API call, a multi-step aggregation. This is a *different* need from repository caching — `BaseRepository`'s `cacheQuery()` caches one model's query; a service result can depend on several models, or none (a third-party API), at once.

**Do not build a second cache system, and do not merge this with `RepositoryCache`.** `BaseService` already composes `HasServiceCache` (itself built on the same `HasCacheable` trait `RepositoryCache` uses internally), and the two layers already share one invalidation mechanism — Laravel's `Cache::tags()` — without any coupling between the classes:

- A repository write flushes a tag string (`{scope}:{table}:listing`, `{scope}:{table}:record:{id}`).
- A service result cached under that *same string* as a dependency is flushed too, automatically, the moment the repository's own writes flush it.
- Neither class knows the other exists. The tag string is the entire contract.

`CacheDependency` builds that string in the exact format `RepositoryCache` uses — never hand-write a tag string, or it will silently drift out of sync with the repository's own tags and stop invalidating:

```php
use CoreFoundation\Repositories\Cache\CacheDependency;

class PricingService extends BaseService
{
    public function __construct(private readonly Product $product) {}

    // Depends on ALL products in scope — busts on any product write, same
    // as a repository listing-tier cache would. Correct when the computation
    // genuinely mixes data from every product (e.g. a store-wide average).
    public function averagePrice(CacheScope $scope): int
    {
        return $this->rememberWithDependencies(
            key: "pricing:average:{$scope->prefix()}",
            dependencies: [CacheDependency::onListing($this->product, $scope)],
            callback: fn () => $this->computeAverage($scope),
        );
    }

    // Depends on ONLY the specific products in the bundle — a write to
    // product #501 never busts this if #501 isn't one of the three.
    public function calculateBundlePrice(array $productIds, CacheScope $scope): int
    {
        return $this->rememberWithDependencies(
            key: 'bundle:'.implode(',', $productIds),
            dependencies: CacheDependency::onRecords($this->product, $productIds, $scope),
            callback: fn () => $this->computePrice($productIds),
        );
    }
}
```

**The "1000 products" trap this closes:** if you need per-item pricing for 1000 products and cache it as one service-level computation over "the whole catalog," you've recreated the listing tier's blast radius by hand — one product update busts your entire cached result. `CacheDependency::onRecords()` with the exact set of IDs the computation actually touched is what keeps the invalidation scoped to what changed, the same way `asRecord($id)` does for a repository's own cached method (see `rules/base-repository.md`'s "Choosing Invalidation Granularity"). If the computation is truly catalog-wide (an average, a total), `onListing()` busting on every write is correct — that's not over-invalidation, it's the computation legitimately depending on every record.

**TTL is for external data, not a substitute for correct dependencies.** Pass `ttl:` only when the result includes data that changes independently of your own models (a third-party price feed, a shipping-rate API) — the effective lifetime becomes `min(ttl, next dependency flush)`. A pure computation over your own models needs no TTL: it lives until a dependency flushes, which is exactly when it should.

## `static::class` in All Registries

`self::class` breaks inheritance — subclasses of the service would share the same pipe registry as the parent.

Incorrect:
```php
protected static array $pipes = [];

public static function addPipe(string $hook, string $pipe): void
{
    static::$pipes[self::class][$hook][] = $pipe;  // wrong
}
```

Correct:
```php
static::$pipes[static::class][$hook][] = $pipe;
```
