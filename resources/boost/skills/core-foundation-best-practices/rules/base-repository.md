# BaseRepository Best Practices

## Every Repository Gets a Contract Interface

A repository is always a pair: `{Name}RepositoryContract` (interface) + `{Name}Repository` (concrete). `php artisan core:make {Name}` generates both automatically the moment `repository` is selected — never generate the concrete class alone.

Services, controllers, and other repositories type-hint the **contract**, never the concrete class. Bind the pair in the module's `ServiceProvider::registerBindings()`:

```php
interface OrderRepositoryContract extends RepositoryContract {}

class OrderRepository extends BaseRepository implements OrderRepositoryContract
{
    protected function setModel(): string
    {
        return Order::class;
    }
}

// OrderServiceProvider::registerBindings()
$this->app->bind(OrderRepositoryContract::class, OrderRepository::class);
```

```php
class OrderService extends BaseService
{
    public function __construct(
        private readonly OrderRepositoryContract $repository,  // interface, not OrderRepository
    ) {}
}
```

Why: this is what makes the repository swappable (a decorator, a caching wrapper, a fake in tests) without touching every class that consumes it — the entire point of the repository pattern. Injecting the concrete class defeats it even if a repository technically exists.

## Always `bind()` — Never `singleton()`

Repositories hold per-request mutable state (pending scopes, eager loads, lock mode). Binding as a singleton leaks state across requests under Octane.

Incorrect:
```php
$this->app->singleton(OrderRepositoryContract::class, OrderRepository::class);
```

Correct:
```php
$this->app->bind(OrderRepositoryContract::class, OrderRepository::class);
```

## Never Query a Model Directly for Business Logic

`Order::where(...)`, `Order::query()`, `Order::find(...)` anywhere in a service, controller, job, or listener bypasses `searchable()`'s SQL-injection guard, `cacheScope()`'s tenant isolation, and every cache invalidation the repository is responsible for. If code needs data, it goes through a repository method — full stop. Direct model queries are acceptable only inside the repository itself, and inside migrations/seeders/factories, which have no repository layer to go through.

## `query()` Is Protected — Not a Public Escape Hatch

`$this->query()` is a fresh `Builder`, but it is only reachable from inside a concrete repository's own methods. It is deliberately **not** part of `RepositoryContract` and not `public` — calling code outside the repository class cannot reach it at all; PHP raises an error if you try.

This is the fix for the single most common way repository caching breaks: an ad hoc query living somewhere that never calls the repository's cache-invalidation methods.

Incorrect — cannot even compile, but shows the shape of the mistake this prevents:
```php
// Inside a service or controller:
$this->userSessionRepository->query()
    ->where('user_id', $user->id)
    ->delete();
// query() is protected — this throws immediately. Even if it didn't: this
// delete() never flushes cache, so every cached read keeps serving the
// deleted rows.
```

Correct — a named method on the concrete repository, with invalidation colocated with the write:
```php
class UserSessionRepository extends BaseRepository implements UserSessionRepositoryContract
{
    public function deleteAllForUser(int $userId): void
    {
        $this->query()->where('user_id', $userId)->delete();
        $this->flushAllCache();
    }
}

// Then from the service:
$this->userSessionRepository->deleteAllForUser($user->id);
```

Add `deleteAllForUser()` to `UserSessionRepositoryContract` so it's part of the documented, swappable surface.

## `searchable()` Is the SQL-Injection Guard

Only columns listed in `searchable()` can be filtered via `FilterApplicator`. Any other column is silently ignored before it reaches the query builder.

```php
protected function searchable(): array
{
    return ['status', 'created_at', 'total'];
    // 'user_id' not listed → silently skipped → cannot be injected via filter params
}
```

## Override `cacheScope()` for Tenant Entities

Without a scope override, cache tags are global. In a multi-tenant app, flushing one tenant's orders would flush all tenants' orders.

```php
protected function cacheScope(): ?CacheScope
{
    return new TenantCacheScope(app(TenantContext::class)->id());
    // All tags become: tenant:{id}:orders:listing, tenant:{id}:orders:record:{id}
}
```

## `update(..., quiet: true)` — Silent Updates, Same Mechanism as Eloquent

Every normal write path (`create()`, `update()`, `updateAtomic()`, `delete()`) automatically flushes the cache it touches. `update()`'s `$quiet` parameter is the one deliberate exception — it does **not** flush cache and does **not** fire Eloquent model events (`updating`/`updated`, observers, broadcasts).

This isn't a bespoke repository mechanism — it's a thin pass-through to `Model::updateQuietly()`, the same method Eloquent itself ships (`saveQuietly()`/`updateQuietly()`/`deleteQuietly()`, all built on `Model::withoutEvents()`). `BaseRepository::update()` just also skips the cache flush to match, since nothing fires to tell the cache layer anything changed:

```php
public function update(int|string $id, array $attributes, bool $quiet = false): Model
{
    $model = $this->model->newQuery()->findOrFail($id);

    if ($quiet) {
        $model->updateQuietly($attributes);   // Model::withoutEvents() under the hood

        return $model;
    }

    $model->update($attributes);
    $this->cache->flushRecord($model, $id, $this->cacheScope());

    return $model;
}
```

```php
// From a service:
$this->userRepository->update($id, ['last_seen_at' => now()], quiet: true);
```

Only reach for it when **both** are true:
1. The column is not present in any cached read, `BaseResource` field, or service-level cache derived from this model.
2. Nothing (an observer, a listener, a broadcast channel) reacts to the column changing.

The textbook case is high-frequency telemetry — `last_seen_at`, `login_count`, a heartbeat timestamp — where flushing cache on every write would thrash it for data nobody actually serves from the cache layer.

Getting this wrong is a correctness bug, not a performance trade-off: `fetchById()`/`fetchAll()` will keep returning the pre-update value indefinitely (or until the cache happens to expire on its own). If the field appears anywhere in a cached response, use `update()` instead.

## Custom Queries Start from `$this->query()` — Inside the Repository Only

Add domain-specific query methods to the concrete repository, starting from the protected `query()`. This is the only correct place `Model::query()`-equivalent logic belongs.

Incorrect:
```php
public function findPending(): Collection
{
    return Order::where('status', 'pending')->get();  // bypasses scopes and contracts
}
```

Correct:
```php
public function findPending(): Collection
{
    return $this->query()->where('status', 'pending')->get();
}
```

## When to Cache a Custom Repository Method

`fetchAll()`/`fetchById()` are cached by default (`cachedMethods()`). A custom method added under "Custom Queries Start from `$this->query()`" above is **not** cached automatically — decide deliberately, don't cache by default and don't skip caching by default either.

**Cache it when both are true:**
1. **Read-heavy relative to writes** — called often, backed by data that changes rarely (a public listing, a per-plan config lookup, anything closer to reference data than to a live ledger).
2. **Safe to serve briefly stale** — nothing breaks if a caller sees a value that's up to a cache miss away from current.

**Don't cache when either is true:**
1. **Write-heavy relative to reads** — the query already runs against a small/cheap dataset and isn't actually slow; caching it only adds invalidation surface for no measured win.
2. **The read feeds a financial or state-changing decision made in the same request** — e.g. reading a balance immediately before deciding how much to deduct from it. A stale read here isn't a UX nitpick, it's a double-spend / race-condition bug. Read live.

```php
// Cache — public plan listing. Hot (every anonymous pricing-page load),
// changes only when an admin edits a plan, staleness for an hour is harmless.
public function listPublic(): Collection
{
    return $this->cacheQuery(__FUNCTION__)
        ->with('entitlements')
        ->remember(fn () => $this->query()->where('is_public', true)->with('entitlements')->get());
}

// Don't cache — SUM() over a handful of rows, read immediately before deciding
// how much credit to apply to an invoice. A stale read here risks over-applying
// credit that's already been spent. It's also already cheap — nothing to gain.
public function getBalance(int $shopId): int
{
    return (int) $this->query()->where('shop_id', $shopId)->sum('amount_cents');
}
```

**When you do cache a custom method, build it with `$this->cacheQuery($method)` — never a bare `Cache::remember()`/`Cache::tags()` call inside a repository, and never `$this->cache->remember(...)` directly either.** `cacheQuery()` returns a `PendingCacheQuery` — a fluent builder (same shape as `Illuminate\Http\Client\PendingRequest`): configure what's unique to the call, then call the terminal `remember()`.

```php
public function getBalance(int $shopId): int
{
    return $this->cacheQuery(__FUNCTION__)
        ->withKey(['shop_id' => $shopId])   // extra cache-key material
        ->asRecord($shopId)                  // record tier, keyed by shop_id — see below
        ->remember(fn () => (int) $this->query()->where('shop_id', $shopId)->sum('amount_cents'));
}
```

`cacheQuery()` already handles `withoutCache()` and an active pessimistic lock for you, the same as `fetchAll()`/`fetchById()` — a method built this way is invalidated for free by `create()`/`update()`/`delete()`, which already flush the tags every `cacheQuery()` entry carries. See `PendingCacheQuery`'s own class docblock for the full fluent surface (`with()`, `criteria()`, `columns()`, `asRecord()`, `dontCache()`, `when()`/`unless()`).

## Choosing Invalidation Granularity — Listing Tier vs. Record Tier

Every `cacheQuery()`/`fetchAll()` read lands in one of two tiers, and picking the right one is the difference between correct caching and quietly overloading your cache layer at scale.

**This is not a bug — read before "fixing" it.** A single `update()` on *any* record busts the entire listing tier for that model+scope — every cached `fetchAll()`/listing-shaped `cacheQuery()` result, not just the one page that happened to contain the changed record. This looks like "1 product update invalidates all 1000 cached products," and it is — but it's the correct, necessary behavior, not a design flaw. A listing's cache key is a hash of criteria/relations/columns; there's no way to know in advance which cached listing variants (different filters, sorts, pages) happen to include the record that just changed. The only options are "bust every listing variant" or "risk serving stale data in some of them," and stale data is the worse failure mode. Do not try to make the listing tier smarter — choose the record tier instead when your access pattern is actually per-item, below.

**If your cached read is a listing** (any filter/sort/pagination combination over a model) — accept that any write to that model busts it. This is `fetchAll()`'s default behavior and it's correct. The fix for "this busts too often" is never a cleverer tag — it's asking whether that data needed to be one big cached listing at all (see the service-layer section in `rules/base-service.md` for the alternative: cache the aggregate as its own dependency-scoped entry, not as a side effect of a listing query).

**If your access pattern is actually per-item** (a shop's balance, a single product's detail page, a per-plan config lookup) — use `asRecord($id)` so only writes to *that* `$id` bust it:

```php
// 1000 products cached individually, each tagged with its own id.
// Updating product #501 busts ONLY product #501's cache — the other 999
// stay warm. This is the record tier working as intended.
public function getProductSummary(int $productId): array
{
    return $this->cacheQuery(__FUNCTION__)
        ->asRecord($productId)
        ->remember(fn () => $this->computeSummary($productId));
}
```

If a write needs to bust one specific `cacheQuery()`-cached entry without also busting the whole model's listing tier, use `flushRecordCache($id)` from a dedicated write method instead of the inherited `create()`'s blanket `flushAllCache()`:

```php
public function recordCredit(array $attributes): ShopCredit
{
    $credit = $this->query()->create($attributes);
    $this->flushRecordCache($attributes['shop_id']);   // only this shop's cache, not every shop's

    return $credit;
}
```

Don't reach for this until the blunt `flushAllCache()`/inherited `create()` is measurably too coarse — same "don't build the granular version until proven necessary" rule as `cacheScope()`.

**A cached computation that genuinely depends on many records at once** (a true aggregate — "average price across all 1000 products," not "these 1000 products individually") is a different problem from either tier above, and belongs at the service layer, not the repository — see `rules/base-service.md`'s "Caching a Service Result" section for how to scope that dependency correctly instead of accidentally busting on every write.

## Fluent Scopes and Eager Loading

Both are chainable and reset after the next call — they never persist across calls.

```php
// Scoped query (trusted caller — not whitelist-gated, unknown name throws immediately)
$orders = $this->repository->scope('active')->fetchAll();

// Eager loading (merges with any explicit $relations argument)
$order = $this->repository->with('items')->fetchById($id);
```
