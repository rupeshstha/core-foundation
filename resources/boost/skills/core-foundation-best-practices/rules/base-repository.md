# BaseRepository Rules

## `setModel()` — Return an Eloquent `Model` Subclass

`setModel()` must return the FQCN of a class that extends `Illuminate\Database\Eloquent\Model`. `BaseRepository` throws `ModelNotInstantiableException` if the model is not an Eloquent model.

Extending `BaseModel` is the recommended path — it unlocks modular extensibility (`addFillable`, `addRelation`, etc.) and automatic searchable column discovery. If you cannot extend `BaseModel` (e.g. the model comes from a third-party package), implement `HasSearchableColumns` and/or `HasRelationRegistry` from `CoreFoundation\Entities\Contracts` to opt in to those specific capabilities.

```php
class OrderRepository extends BaseRepository implements RepositoryContract
{
    protected function setModel(): string
    {
        return Order::class; // Order extends BaseModel (recommended) or any Eloquent Model
    }
}
```

## Contract Selection — Implement Only What You Need

| Scenario | Implement |
|---|---|
| Full CRUD + custom queries | `RepositoryContract` |
| Read-only (reports, analytics) | `ReadRepositoryContract` |
| Write-only (event sourcing) | `WriteRepositoryContract` |
| Custom queries only | `QueryRepositoryContract` |

## `searchable()` — SQL Injection Guard

Only columns listed in `searchable()` can be filtered. Any other column is silently ignored by `FilterApplicator`. Always whitelist explicitly.

```php
protected function searchable(): array
{
    return ['user_id', 'status', 'total', 'created_at', ...Order::getSearchable()];
    //                                                    ^^^ picks up module-added columns
}
```

## Custom Queries — Always `$this->query()`

Never call `Model::query()` directly inside a repository method.

Incorrect:
```php
public function pendingOlderThan(int $days): Collection
{
    return Order::query()->where('status', 'pending')->where(...)->get();
}
```

Correct:
```php
public function pendingOlderThan(int $days): Collection
{
    return $this->query()
        ->where('status', 'pending')
        ->where('created_at', '<', now()->subDays($days))
        ->get();
}
```

## Binding — Always `bind()`, Never `singleton()` or `scoped()`

Repositories carry mutable state (loaded relations, cache state). Always register as transient.

Incorrect:
```php
$this->app->singleton(OrderRepository::class);
$this->app->scoped(OrderRepository::class);
```

Correct:
```php
$this->app->bind(OrderRepository::class);
// or via BaseExtensionServiceProvider:
protected function registerBindings(): void
{
    $this->app->bind(OrderRepository::class);
}
```

## Cache Configuration

```php
// Override to add or remove cached methods (default: fetchAll, fetchById)
protected function cachedMethods(): array
{
    return ['fetchAll', 'fetchById', 'pendingOlderThan'];
}
```

Cache invalidation rules:
- After `create` / `delete` → `flushModel()` (full tag flush)
- After `update` → `flushRecord()` (granular, single record)
- Register `RepositoryCacheObserver` in `boot()` for automatic cache busting

Bypass cache for one call:
```php
// Inside a concrete repository method:
return $this->withoutCache()->fetchAll($filters);
```

## Built-in Filter Operators

Pass via `fetchAll(filters: ['filters' => [...]])`:

| Identifier | Meaning | Example value |
|---|---|---|
| `__eq_` | = | `'pending'` |
| `__neq_` | != | `'cancelled'` |
| `__gt_` / `__gte_` | > / >= | `'100'` |
| `__lt_` / `__lte_` | < / <= | `'50'` |
| `__like_` | LIKE | `'%acme%'` |
| `__null_` / `__nnull_` | IS NULL / IS NOT NULL | `'1'` |
| `__in_` / `__nin_` | IN / NOT IN | `'1,2,3'` |

## Adding a Custom Filter Operator

```php
final class BetweenOperator implements FilterOperator
{
    public function identifier(): string { return '__between_'; }

    public function apply(Builder $query, string $column, mixed $value): Builder
    {
        [$min, $max] = explode(',', $value, 2);
        return $query->whereBetween($column, [$min, $max]);
    }
}

// Register from ServiceProvider::extendOperators():
protected function extendOperators(): void
{
    $this->operator(new BetweenOperator);
}
```

## Extending Searchable Columns From Another Module

```php
// In SubscriptionServiceProvider::extendModels():
Order::addSearchable(['subscription_id', 'plan_code']);
// Now those columns are available for filtering in OrderRepository
```

## Race Condition & Locking

`BaseRepository` provides built-in mechanisms to handle race conditions safely at the database level.

### Pessimistic Locking
Locks the selected rows from being updated (or read depending on lock type) until the current transaction commits. Calling `lockForUpdate()` or `sharedLock()` configures the repository to apply a database-level lock to the **very next read query** (`fetchAll` or `fetchById`). It also safely bypasses the cache.

```php
// Apply a FOR UPDATE lock on the record
$order = $this->repository->lockForUpdate()->fetchById($id);

// Apply a FOR SHARE lock on the record
$order = $this->repository->sharedLock()->fetchById($id);
```
**Rule:** Pessimistic locks must be executed inside an active database transaction (`DB::transaction`).

### Atomic Updates (Compare-and-Swap)
Protects against lost updates by ensuring the database record still matches an expected state before applying the update. This is the preferred architectural pattern for optimistic concurrency control as it is schema-neutral.

```php
// Performs an atomic update ONLY IF the status is still 'pending'
$this->repository->updateAtomic(
    id: $id, 
    attributes: ['status' => 'processing', 'locked_at' => now()], 
    conditions: ['status' => 'pending']
);
```

**Opt-in Versioning:** If a specific resource requires version-based locking, you can explicitly include a version check. This avoids forcing a version column on every table in the system.

```php
$this->repository->updateAtomic(
    id: $id, 
    attributes: ['balance' => 100, 'version' => $v + 1], 
    conditions: ['version' => $v]
);
```
**Rule:** Use `updateAtomic` for high-concurrency resources where you need to guarantee state transitions (e.g., Status flows, Inventory, Wallets). If the conditions are no longer met, it throws `StaleDataException` (HTTP 409 Conflict).
