# BaseRepository Best Practices

## Always `bind()` — Never `singleton()`

Repositories hold per-request mutable state (pending scopes, eager loads, lock mode). Binding as a singleton leaks state across requests under Octane.

Incorrect:
```php
$this->app->singleton(OrderRepository::class);
```

Correct:
```php
$this->app->bind(OrderRepository::class);
```

## `searchable()` Is the SQL-Injection Guard

Only columns listed in `searchable()` can be filtered via `FilterApplicator`. Any other column is silently ignored before it reaches the query builder.

```php
protected function searchable(): array
{
    return ['status', 'created_at', 'total'];
    // 'user_id' not listed → silently skipped → cannot be injected via filter params
}
```

## Custom Queries Start from `$this->query()`

`$this->query()` is the entry point declared by `QueryRepositoryContract`. It bootstraps the correct model, applies global scopes, and is the safe starting point for custom queries.

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

## Override `cacheScope()` for Tenant Entities

Without a scope override, cache tags are global. In a multi-tenant app, flushing one tenant's orders would flush all tenants' orders.

```php
protected function cacheScope(): ?CacheScope
{
    return new TenantCacheScope(app(TenantContext::class)->id());
    // All tags become: tenant:{id}:orders:listing, tenant:{id}:orders:record:{id}
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
