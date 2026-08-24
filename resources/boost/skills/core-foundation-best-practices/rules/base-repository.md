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

## `updateQuietly()` — Silent Updates, Used Sparingly

Every normal write path (`create()`, `update()`, `updateAtomic()`, `delete()`) automatically flushes the cache it touches. `updateQuietly()` is the one deliberate exception: it updates a record **without** flushing cache and **without** firing Eloquent model events (`updating`/`updated`, observers, broadcasts).

It is not part of `WriteRepositoryContract` — it must be opted into per repository by adding it to that repository's own contract:

```php
interface UserRepositoryContract extends RepositoryContract
{
    public function updateQuietly(int|string $id, array $attributes): Model;
}
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

## Fluent Scopes and Eager Loading

Both are chainable and reset after the next call — they never persist across calls.

```php
// Scoped query (trusted caller — not whitelist-gated, unknown name throws immediately)
$orders = $this->repository->scope('active')->fetchAll();

// Eager loading (merges with any explicit $relations argument)
$order = $this->repository->with('items')->fetchById($id);
```
