<?php

namespace CoreFoundation\Repositories;

use CoreFoundation\Traits\HasEvent;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use CoreFoundation\Repositories\Cache\QueryType;
use CoreFoundation\Exceptions\StaleDataException;
use CoreFoundation\Repositories\Cache\CacheScope;
use CoreFoundation\Repositories\Sort\SortApplicator;
use CoreFoundation\Repositories\Cache\RepositoryCache;
use CoreFoundation\Repositories\Filter\FilterApplicator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use CoreFoundation\Entities\Contracts\HasSearchableColumns;
use CoreFoundation\Repositories\Contracts\RepositoryContract;
use CoreFoundation\Repositories\Exceptions\ModelNotInstantiableException;

/**
 * BaseRepository
 *
 * Foundation for all Eloquent repositories. Composes:
 *   - FilterApplicator    — operator-based query filtering
 *   - SortApplicator      — request-driven sorting
 *   - RepositoryCache     — tag-based cache-first strategy with smart invalidation
 *   - HasEvent            — domain event dispatch around write operations
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ DEFINING A REPOSITORY                                                       │
 * │                                                                             │
 * │   class OrderRepository extends BaseRepository                              │
 * │   {                                                                         │
 * │       protected function setModel(): string                                 │
 * │       {                                                                     │
 * │           return Order::class;                                              │
 * │       }                                                                     │
 * │                                                                             │
 * │       // Override searchable columns (or add on the model via $searchable)  │
 * │       protected function searchable(): array                                │
 * │       {                                                                     │
 * │           return ['status', 'created_at', ...Order::getSearchable()];       │
 * │       }                                                                     │
 * │                                                                             │
 * │       // Override which methods cache their results                         │
 * │       protected function cachedMethods(): array                             │
 * │       {                                                                     │
 * │           return ['fetchAll', 'fetchById'];                                 │
 * │       }                                                                     │
 * │                                                                             │
 * │       // Custom query (implements QueryRepositoryContract)                  │
 * │       public function pendingOlderThan(int $days): Collection               │
 * │       {                                                                     │
 * │           return $this->query()                                             │
 * │               ->where('status', 'pending')                                  │
 * │               ->where('created_at', '<', now()->subDays($days))             │
 * │               ->get();                                                      │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ EXTENDING FILTER OPERATORS (from ServiceProvider::boot())                   │
 * │                                                                             │
 * │   FilterApplicator::addOperator(new BetweenOperator);                       │
 * │   Order::addSearchable(['subscription_id']);                                 │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
abstract class BaseRepository implements RepositoryContract
{
    use HasEvent;

    protected Model $model;

    /**
     * Active pessimistic lock mode for the next query.
     *
     * @var string|false 'update', 'shared', or false
     */
    protected string|false $lockMode = false;

    /**
     * Per-repository cache bypass flag.
     *
     * Scoped to this repository instance only — does NOT touch the shared
     * RepositoryCache instance, so other repositories in the same request
     * are unaffected. Consumed and reset on the next read call.
     */
    private bool $bypassCache = false;

    public function __construct(
        protected readonly Application $app,
        protected readonly FilterApplicator $filterApplicator,
        protected readonly SortApplicator $sortApplicator,
        protected readonly RepositoryCache $cache,
    ) {
        $this->boot();
    }

    // =========================================================================
    // Pessimistic Locking
    // =========================================================================

    public function lockForUpdate(): static
    {
        $this->lockMode = 'update';

        return $this;
    }

    public function sharedLock(): static
    {
        $this->lockMode = 'shared';

        return $this;
    }

    protected function applyLock(Builder $query): void
    {
        if ($this->lockMode === 'update') {
            $query->lockForUpdate();
        } elseif ($this->lockMode === 'shared') {
            $query->sharedLock();
        }

        $this->lockMode = false;
    }

    /**
     * Return the FQCN of the model this repository manages.
     */
    abstract protected function setModel(): string;

    /**
     * Columns allowed for filtering in this repository.
     *
     * Default: merges the model's own $searchable with module-added columns.
     * Override to restrict or extend per-repository.
     *
     * @return array<string>
     */
    protected function searchable(): array
    {
        return $this->model instanceof HasSearchableColumns
            ? $this->model::getSearchable()
            : [];
    }

    /**
     * Columns allowed for sorting in this repository.
     *
     * Default: same as searchable. Override to restrict.
     *
     * @return array<string>
     */
    protected function sortable(): array
    {
        return $this->searchable();
    }

    /**
     * Repository methods whose results should be cached.
     *
     * Default: fetchAll and fetchById.
     * Override to add or remove methods from the cached set.
     *
     * @return array<string>
     */
    protected function cachedMethods(): array
    {
        return ['fetchAll', 'fetchById'];
    }

    /**
     * Cache isolation scope for this repository.
     *
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ SECURITY — MULTI-TENANT REPOS MUST OVERRIDE THIS                        │
     * │                                                                         │
     * │ The default (null) produces a global cache key with NO tenant prefix:   │
     * │   users:fetchAll:{hash}                                                 │
     * │                                                                         │
     * │ Two tenants sending identical queries share the same key. Tenant B      │
     * │ gets Tenant A's cached result — a data isolation breach.                │
     * │                                                                         │
     * │ Null is only safe for cross-tenant data: plans, feature flags,          │
     * │ reference tables. Every domain model repo must return a scope.          │
     * └─────────────────────────────────────────────────────────────────────────┘
     *
     *   // In a tenant-scoped repo:
     *   protected function cacheScope(): CacheScope
     *   {
     *       return new TenantCacheScope($this->resolveTenantId());
     *   }
     *
     * The scope prefixes all read (remember) and write (flush) operations.
     */
    protected function cacheScope(): ?CacheScope
    {
        return null;
    }

    public function fetchAll(
        array $criteria = [],
        array $relations = [],
        array $columns = ['*'],
        bool $paginate = true,
        int $perPage = 25,
    ): Collection|LengthAwarePaginator {
        // Events fire on every call including cache hits. Listeners must be
        // idempotent — avoid side effects (counters, audit writes) here.
        $this->dispatch('fetch-all.before', compact('criteria', 'relations'));

        $isLocked          = $this->lockMode !== false;
        $bypass            = $this->bypassCache;
        $this->bypassCache = false;

        // Paginated results are request-bound (current page lives in the request).
        // Caching them would return page 1 data for every subsequent page request
        // that shares the same criteria. Only cache non-paginated collections.
        $shouldCache = $this->isCached(__FUNCTION__) && ! $bypass && ! $isLocked && ! $paginate;

        if ($this->isCached(__FUNCTION__) && $paginate) {
            Log::debug('[Cache] fetchAll bypassed — paginate:true on a cached method. Pass paginate:false to cache.', [
                'model' => $this->model::class,
            ]);
        }

        $result = $this->cache->remember(
            model: $this->model,
            method: __FUNCTION__,
            criteria: $criteria,
            relations: $relations,
            columns: $columns,
            extra: [],
            shouldCache: $shouldCache,
            queryType: QueryType::Listing,
            scope: $this->cacheScope(),
            callback: function () use ($criteria, $relations, $columns, $paginate, $perPage) {
                /** @var Builder<Model> $query */
                $query = $this->model->newQuery();
                $query->select($columns);

                $this->applyLock($query);

                if ($relations) {
                    $query->with($relations);
                }

                $this->filterApplicator->apply($query, $criteria['filters'] ?? [], $this->searchable());
                $this->sortApplicator->apply($query, $criteria['sort'] ?? [], $this->sortable());

                return $paginate
                    ? $query->paginate($perPage)
                    : $query->get();
            },
        );

        $this->dispatch('fetch-all.after', $result);

        return $result;
    }

    public function fetchById(
        int|string $id,
        array $relations = [],
        array $columns = ['*'],
    ): ?Model {
        $this->dispatch('fetch-by-id.before', compact('id', 'relations'));

        $isLocked          = $this->lockMode !== false;
        $bypass            = $this->bypassCache;
        $this->bypassCache = false;
        $shouldCache       = $this->isCached(__FUNCTION__) && ! $bypass && ! $isLocked;

        $result = $this->cache->remember(
            model: $this->model,
            method: __FUNCTION__,
            criteria: [],
            relations: $relations,
            columns: $columns,
            extra: ['id' => $id],
            shouldCache: $shouldCache,
            queryType: QueryType::Record,
            recordId: $id,
            scope: $this->cacheScope(),
            callback: function () use ($id, $relations, $columns) {
                /** @var Builder<Model> $query */
                $query = $this->model->newQuery();
                $query->select($columns);

                $this->applyLock($query);

                if ($relations) {
                    $query->with($relations);
                }

                return $query->find($id);
            },
        );

        $this->dispatch('fetch-by-id.after', $result);

        return $result;
    }

    public function create(array $attributes): Model
    {
        $this->dispatch('create.before', $attributes);

        $model = $this->model->newQuery()->create($attributes);

        // Observer handles cache invalidation by default.
        // Call explicitly here only if the observer is not registered.
        // $this->cache->flushModel($this->model);

        $this->dispatch('create.after', $model);

        return $model;
    }

    public function update(int|string $id, array $attributes): Model
    {
        $this->dispatch('update.before', compact('id', 'attributes'));

        $model = $this->model->newQuery()->findOrFail($id);
        $model->update($attributes);

        // Observer handles cache invalidation by default.
        // Call explicitly: $this->cache->flushRecord($model, $id);

        $this->dispatch('update.after', $model);

        return $model;
    }

    public function updateAtomic(int|string $id, array $attributes, array $conditions): Model
    {
        $this->dispatch('update-atomic.before', compact('id', 'attributes', 'conditions'));

        $model = $this->model->newQuery()->findOrFail($id);

        $affected = $this->model->newQuery()
            ->where($model->getKeyName(), $id)
            ->where($conditions)
            ->update($attributes);

        // A zero affected count could mean either:
        // 1. Stale data (conditions didn't match)
        // 2. Data was already identical (no-op update)
        if ($affected === 0) {
            $isStillValid = $this->model->newQuery()
                ->where($model->getKeyName(), $id)
                ->where($conditions)
                ->exists();

            if (! $isStillValid) {
                throw new StaleDataException;
            }
        }

        // Direct updates bypass Eloquent observers, so we must flush cache manually.
        $this->cache->flushRecord($model, $id, $this->cacheScope());

        $model->refresh();

        $this->dispatch('update-atomic.after', $model);

        return $model;
    }

    public function delete(int|string $id): bool
    {
        $this->dispatch('delete.before', compact('id'));

        $model = $this->model->newQuery()->findOrFail($id);
        $result = (bool) $model->delete();

        // Observer handles cache invalidation by default.
        // Call explicitly: $this->cache->flushModel($this->model);

        $this->dispatch('delete.after', compact('id', 'result'));

        return $result;
    }

    /**
     * Return a fresh Builder for custom queries.
     * Starting point for all domain-specific queries in concrete repositories.
     */
    public function query(): Builder
    {
        return $this->model::query();
    }

    // =========================================================================
    // RepositoryContract — Model access
    // =========================================================================

    public function getModel(): Model
    {
        return $this->model;
    }

    /**
     * Bypass cache for the next read call on this repository only.
     *
     * Sets a flag that is consumed and reset by the next fetchAll() or
     * fetchById() call. Does NOT touch the shared RepositoryCache instance,
     * so other repositories in the same request are completely unaffected.
     */
    final protected function withoutCache(): static
    {
        $this->bypassCache = true;

        return $this;
    }

    /**
     * Manually flush all cache for this model.
     * Call after bulk operations that bypass individual model events.
     */
    final protected function flushCache(): void
    {
        $this->cache->flushModel($this->model, $this->cacheScope());
    }

    /**
     * Flush ALL cache for this model within scope — all tiers, all records.
     * Use after bulk operations that bypass individual model events.
     */
    final protected function flushAllCache(): void
    {
        $this->cache->flushAll($this->model, $this->cacheScope());
    }

    /**
     * Boot the repository — resolve and validate the model.
     * Models are NOT singletons — a fresh instance per repository is correct
     * since models carry mutable state (dirty attributes, loaded relations).
     */
    private function boot(): void
    {
        $modelClass = $this->setModel();

        // Make a fresh instance — do NOT use app()->singleton() for models
        $instance = $this->app->make($modelClass);

        throw_unless(
            condition: $instance instanceof Model,
            exception: new ModelNotInstantiableException(
                "[{$modelClass}] must extend Illuminate\\Database\\Eloquent\\Model."
            ),
        );

        $this->model = $instance;
        $this->eventPrefix = $this->model->getTable();
    }

    private function isCached(string $method): bool
    {
        return in_array($method, $this->cachedMethods(), true);
    }
}
