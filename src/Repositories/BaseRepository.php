<?php

namespace CoreFoundation\Repositories;

use CoreFoundation\Traits\HasEvent;
use CoreFoundation\Entities\Contracts\HasSearchableColumns;
use Illuminate\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use CoreFoundation\Repositories\Sort\SortApplicator;
use CoreFoundation\Repositories\Cache\CacheScope;
use CoreFoundation\Repositories\Cache\QueryType;
use CoreFoundation\Repositories\Cache\RepositoryCache;
use CoreFoundation\Repositories\Filter\FilterApplicator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
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

    public function __construct(
        protected readonly Application $app,
        protected readonly FilterApplicator $filterApplicator,
        protected readonly SortApplicator $sortApplicator,
        protected readonly RepositoryCache $cache,
    ) {
        $this->boot();
    }

    // =========================================================================
    // Contract — child must implement
    // =========================================================================

    /**
     * Return the FQCN of the model this repository manages.
     */
    abstract protected function setModel(): string;

    // =========================================================================
    // Extension points
    // =========================================================================

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
     * Return a CacheScope to isolate all cache operations within a boundary
     * (e.g. tenant). Null = global cache with no isolation (default).
     *
     * Override in concrete repositories to enable tenant-aware caching:
     *
     *   protected function cacheScope(): CacheScope
     *   {
     *       return new TenantCacheScope($this->resolveTenantId());
     *   }
     *
     * The scope is used for both read (remember) and write (flush) operations.
     * For writes via the observer, scope is derived from the model's attributes.
     */
    protected function cacheScope(): ?CacheScope
    {
        return null;
    }

    // =========================================================================
    // RepositoryContract — Read
    // =========================================================================

    public function fetchAll(
        array $filters = [],
        array $relations = [],
        array $columns = ['*'],
        bool $paginate = true,
        int $perPage = 25,
    ): Collection|LengthAwarePaginator {
        $this->dispatch('fetch-all.before', compact('filters', 'relations'));

        $result = $this->cache->remember(
            model: $this->model,
            method: __FUNCTION__,
            filters: $filters,
            relations: $relations,
            columns: $columns,
            extra: ['paginate' => $paginate, 'per_page' => $perPage],
            shouldCache: $this->isCached(__FUNCTION__),
            queryType: QueryType::Listing,
            scope: $this->cacheScope(),
            callback: function () use ($filters, $relations, $columns, $paginate, $perPage) {
                $query = $this->model::select($columns);

                if ($relations) {
                    $query->with($relations);
                }

                $this->filterApplicator->apply($query, $filters['filters'] ?? [], $this->searchable());
                $this->sortApplicator->apply($query, $filters['sort'] ?? [], $this->sortable());

                return $paginate
                    ? $query->paginate($perPage)->appends(request()->except('page'))
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

        $result = $this->cache->remember(
            model: $this->model,
            method: __FUNCTION__,
            filters: [],
            relations: $relations,
            columns: $columns,
            extra: ['id' => $id],
            shouldCache: $this->isCached(__FUNCTION__),
            queryType: QueryType::Record,
            recordId: $id,
            scope: $this->cacheScope(),
            callback: function () use ($id, $relations, $columns) {
                $query = $this->model::select($columns);

                if ($relations) {
                    $query->with($relations);
                }

                return $query->find($id);
            },
        );

        $this->dispatch('fetch-by-id.after', $result);

        return $result;
    }

    // =========================================================================
    // RepositoryContract — Write
    // =========================================================================

    public function create(array $attributes): Model
    {
        $this->dispatch('create.before', $attributes);

        $model = $this->model::create($attributes);

        // Observer handles cache invalidation by default.
        // Call explicitly here only if the observer is not registered.
        // $this->cache->flushModel($this->model);

        $this->dispatch('create.after', $model);

        return $model;
    }

    public function update(int|string $id, array $attributes): Model
    {
        $this->dispatch('update.before', compact('id', 'attributes'));

        $model = $this->model::findOrFail($id);
        $model->update($attributes);

        // Observer handles cache invalidation by default.
        // Call explicitly: $this->cache->flushRecord($model, $id);

        $this->dispatch('update.after', $model);

        return $model;
    }

    public function delete(int|string $id): bool
    {
        $this->dispatch('delete.before', compact('id'));

        $model = $this->model::findOrFail($id);
        $result = (bool) $model->delete();

        // Observer handles cache invalidation by default.
        // Call explicitly: $this->cache->flushModel($this->model);

        $this->dispatch('delete.after', compact('id', 'result'));

        return $result;
    }

    // =========================================================================
    // RepositoryContract — Query
    // =========================================================================

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

    // =========================================================================
    // Cache control — callable from concrete repositories
    // =========================================================================

    /**
     * Bypass cache for the next call.
     * Useful when fresh data is required regardless of cache state.
     */
    final protected function withoutCache(): static
    {
        $this->cache->disable();

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

    // =========================================================================
    // Internals
    // =========================================================================

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
