<?php

namespace CoreFoundation\Repositories;

use Closure;
use InvalidArgumentException;
use Illuminate\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\Repositories\Cache\QueryType;
use CoreFoundation\Exceptions\StaleDataException;
use CoreFoundation\Repositories\Cache\CacheScope;
use CoreFoundation\Repositories\Sort\SortApplicator;
use CoreFoundation\Repositories\Cache\RepositoryCache;
use CoreFoundation\Repositories\Scope\ScopeApplicator;
use CoreFoundation\Repositories\Filter\FilterApplicator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use CoreFoundation\Entities\Contracts\HasSearchableColumns;
use CoreFoundation\Repositories\Contracts\RepositoryContract;
use CoreFoundation\Repositories\Exceptions\ModelNotInstantiableException;

abstract class BaseRepository implements RepositoryContract
{
    /**
     * Per-repository cache bypass flag.
     *
     * Scoped to this repository instance only — does NOT touch the shared
     * RepositoryCache instance, so other repositories in the same request
     * are unaffected. Consumed and reset on the next read call.
     */
    private bool $bypassCache = false;

    /**
     * Pending fluent scopes for the next read call on this repository only.
     * Set via scope(), consumed and reset by the next fetchAll() or fetchById() call.
     *
     * @var array<string, array>
     */
    private array $pendingScopes = [];

    /**
     * Pending fluent eager-loads for the next read call on this repository only.
     * Set via with(), consumed and reset by the next fetchAll() or fetchById() call.
     *
     * @var array<string>
     */
    private array $pendingRelations = [];

    /**
     * Scope names registered by other modules, keyed by the concrete
     * repository class (static::class) they were added to. Same pattern as
     * ModelFillables::$additionalFillable / ModelSearchable::$additionalSearchable —
     * lets Module B extend Module A's repository whitelist without touching
     * Module A's source.
     *
     * @var array<class-string, array<string>>
     */
    protected static array $additionalScopeable = [];

    protected Model $model;

    /**
     * Active pessimistic lock mode for the next query.
     *
     * @var string|false 'update', 'shared', or false
     */
    protected string|false $lockMode = false;

    public function __construct(
        protected readonly Application $app,
        protected readonly FilterApplicator $filterApplicator,
        protected readonly SortApplicator $sortApplicator,
        protected readonly ScopeApplicator $scopeApplicator,
        protected readonly RepositoryCache $cache,
    ) {
        $this->boot();
    }

    /**
     * Return the FQCN of the model this repository manages.
     */
    abstract protected function setModel(): string;

    /**
     * Pessimistic Locking
     */
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
     * Apply a named Eloquent local scope to the next fetchAll() or fetchById()
     * call on this repository instance. Chainable — call multiple times to
     * stack scopes.
     *
     * Resolves directly against the model via Laravel's native
     * Builder::scopes() — NOT whitelist-gated. This is a direct call from
     * application code (a service), not request input, so it follows the
     * same trust model as lockForUpdate()/sharedLock(): the caller is
     * trusted, and an unknown scope name throws BadMethodCallException
     * immediately, same as calling the scope directly on the model would.
     *
     * Use criteria['scopes'] on fetchAll() instead for request-driven
     * scoping (e.g. a `?scopes[]=active` query string) — that path is
     * whitelist-gated via scopeable() because the input is untrusted.
     *
     *   $this->userRepository->scope('active')->fetchById($id);
     *   $this->userRepository->scope('ofType', ['admin'])->fetchAll();
     *   $this->userRepository->scope('active')->scope('verified')->fetchAll();
     *
     * @param  array  $arguments  Positional arguments forwarded to the scope method
     */
    public function scope(string $name, array $arguments = []): static
    {
        $this->pendingScopes[$name] = $arguments;

        return $this;
    }

    /**
     * Apply a snapshot of pending fluent scopes to the given Builder.
     * Takes the snapshot as a parameter rather than reading $this->pendingScopes
     * directly — the caller resets the instance property up front (before the
     * cache layer decides hit or miss) so a cache hit can never skip the reset.
     */
    private function applyScope(Builder $query, array $scopes): void
    {
        if ($scopes === []) {
            return;
        }

        $query->scopes($scopes);
    }

    /**
     * Eager-load relations on the next fetchAll() or fetchById() call, without
     * threading a $relations array through every call site. Chainable — call
     * multiple times to stack relations, same as Eloquent's own with().
     *
     * Merges with the $relations parameter passed directly to fetchAll()/
     * fetchById() — neither replaces the other — and participates in the
     * cache key exactly like an explicit $relations argument would, so a
     * call with extra eager-loads never collides with one without them.
     *
     *   $this->orderRepository->with('items')->fetchById($id);
     *   $this->orderRepository->with(['items', 'customer'])->fetchAll();
     *
     * @param  array<string>|string  $relations
     */
    public function with(array|string $relations): static
    {
        $this->pendingRelations = array_values(array_unique(array_merge(
            $this->pendingRelations,
            is_array($relations) ? $relations : [$relations],
        )));

        return $this;
    }

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
     * Eloquent local scope names this repository allows callers to apply via
     * criteria['scopes'] — e.g. ['active', 'recent'] invokes Model::scopeActive()
     * and Model::scopeRecent().
     *
     * Default: none. Unlike searchable()/sortable(), there is no model-level
     * source to derive this from — every scope must be explicitly opted in,
     * since a local scope can run arbitrary query logic, not just compare a
     * column. Override per-repository to declare this repository's own base
     * list — to add MORE scopes from another module, use addScopeable()
     * instead of editing this method (see below).
     *
     * @return array<string>
     */
    protected function scopeable(): array
    {
        return [];
    }

    /**
     * Register additional scope names for this repository from an external
     * module, without touching the repository's source — same pattern as
     * Model::addFillable()/addSearchable().
     *
     * In a modular monolith, Module B (e.g. Promotions) may need
     * ProductRepository (owned by Module A, Catalog) to allow an extra scope.
     * Without this, Module B would have to edit Module A's repository class
     * directly to override scopeable() — breaking module isolation.
     *
     *   // From PromotionsServiceProvider::boot():
     *   ProductRepository::addScopeable(['onSale', 'lowStock']);
     *
     * Keyed by static::class (late static binding) — registering on
     * ProductRepository never leaks into a sibling repository's whitelist.
     *
     * Only gates criteria['scopes'] (request-driven input). The fluent
     * scope() method is intentionally unwhitelisted — see its docblock.
     *
     * @param  array<string>  $scopes
     */
    public static function addScopeable(array $scopes): void
    {
        static::$additionalScopeable[static::class] = array_unique(array_merge(
            static::$additionalScopeable[static::class] ?? [],
            $scopes,
        ));
    }

    /**
     * All scope names this repository allows via criteria['scopes'] — its own
     * scopeable() declaration merged with anything registered by other
     * modules via addScopeable(). This is the list actually enforced.
     *
     * @return array<string>
     */
    private function resolveScopeable(): array
    {
        return array_values(array_unique(array_merge(
            $this->scopeable(),
            static::$additionalScopeable[static::class] ?? [],
        )));
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
        return config('repository.cache.methods', ['fetchAll', 'fetchById']);
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
     *       return new PrefixCacheScope("tenant:{$this->resolveTenantId()}");
     *   }
     *
     * The scope prefixes all read (remember) and write (flush) operations.
     */
    protected function cacheScope(): ?CacheScope
    {
        return null;
    }

    /**
     * Return a fresh Builder for custom queries.
     *
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ PROTECTED ON PURPOSE — DO NOT WIDEN TO public                           │
     * │                                                                         │
     * │ This is the entry point for custom queries defined as NAMED METHODS     │
     * │ on a concrete repository — never a query built ad hoc from outside      │
     * │ the repository class (a service, a controller, an event listener).      │
     * │                                                                         │
     * │ If it were public, nothing would stop code like this from living        │
     * │ anywhere in the app, silently bypassing every cache invalidation the    │
     * │ repository is responsible for:                                          │
     * │                                                                         │
     * │   $this->userSessionRepository->query()                                 │
     * │       ->where('user_id', $user->id)                                     │
     * │       ->delete();                                                       │
     * │                                                                         │
     * │ That delete() never calls flushRecord()/flushAll() — every cached       │
     * │ fetchAll()/fetchById() result for this model keeps serving deleted      │
     * │ rows until the cache naturally expires (or never, if uncached).         │
     * │                                                                         │
     * │ CORRECT — add a named method to the concrete repository, next to the    │
     * │ other query methods, so cache invalidation stays colocated with the     │
     * │ write:                                                                  │
     * │                                                                         │
     * │   class UserSessionRepository extends BaseRepository                    │
     * │       implements UserSessionRepositoryContract                          │
     * │   {                                                                     │
     * │       public function deleteAllForUser(int $userId): void               │
     * │       {                                                                 │
     * │           $this->query()->where('user_id', $userId)->delete();          │
     * │           $this->flushAllCache();                                       │
     * │       }                                                                 │
     * │   }                                                                     │
     * │                                                                         │
     * │ Then the service calls the named method, never query() directly:        │
     * │   $this->userSessionRepository->deleteAllForUser($user->id);            │
     * └─────────────────────────────────────────────────────────────────────────┘
     *
     * @return Builder<Model>
     */
    protected function query(): Builder
    {
        return $this->model::query();
    }

    /**
     * Return the underlying model instance.
     */
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
     * Flush RECORD + LISTING cache for one identifiable value — the granular
     * sibling of flushAllCache(). $id does not have to be this model's own
     * primary key: it only has to match the $recordId a corresponding
     * cacheQuery() call was tagged with, so it also works for a custom
     * aggregate keyed by something else (e.g. a shop_id on a ledger model).
     *
     * Prefer this over flushAllCache() from a custom write method when only
     * one identifiable result needs busting — flushAllCache() invalidates
     * every cached read for the model+scope, not just the one that changed.
     */
    final protected function flushRecordCache(int|string $id): void
    {
        $this->cache->flushRecord($this->model, $id, $this->cacheScope());
    }

    /**
     * Cache the result of a custom query method with the same semantics
     * fetchAll()/fetchById() already get for free: honours withoutCache(),
     * and never caches while a pessimistic lock is active for the next query.
     *
     * Fills in everything the repository already knows about itself — the
     * model and cacheScope() — so a custom method only supplies what's
     * actually unique to it: a callback, and (when the method takes
     * arguments) enough in $extra to keep the cache key distinct per call.
     *
     * Invalidation is automatic and requires no extra code as long as writes
     * go through create()/update()/delete() (or flushCache()/flushAllCache()/
     * flushRecordCache() from a custom write method) — every entry this
     * produces carries the model's base tag, the same one those flush calls
     * already bust.
     *
     *   // Listing-tier — busted by any create()/update()/delete() on this model.
     *   public function listPublic(): Collection
     *   {
     *       return $this->cacheQuery(
     *           method: __FUNCTION__,
     *           callback: fn () => $this->query()->where('is_public', true)->get(),
     *       );
     *   }
     *
     *   // Record-tier keyed by an explicit argument, not this model's own PK —
     *   // lets a future flushRecordCache($shopId) target just this shop.
     *   public function getBalance(int $shopId): int
     *   {
     *       return $this->cacheQuery(
     *           method: __FUNCTION__,
     *           callback: fn () => (int) $this->query()->where('shop_id', $shopId)->sum('amount_cents'),
     *           extra: ['shop_id' => $shopId],
     *           queryType: QueryType::Record,
     *           recordId: $shopId,
     *       );
     *   }
     *
     * @param  Closure(): mixed  $callback  Runs only on a cache miss
     * @param  array<string, mixed>  $extra  Extra discriminators baked into the cache key — anything the method's own arguments contribute (e.g. ['shop_id' => $shopId])
     * @param  array<string>  $relations  Eager-loaded relation names, if any — also feeds relation-based invalidation, not just key uniqueness
     * @param  array<string>  $columns  Selected columns, if the method varies them
     * @param  int|string|null  $recordId  Required when $queryType is Record; need not be this model's own primary key
     */
    final protected function cacheQuery(
        string $method,
        Closure $callback,
        array $extra = [],
        array $relations = [],
        array $columns = ['*'],
        QueryType $queryType = QueryType::Listing,
        int|string|null $recordId = null,
    ): mixed {
        $isLocked = $this->lockMode !== false;
        $bypass = $this->bypassCache;
        $this->bypassCache = false;

        return $this->cache->remember(
            model: $this->model,
            method: $method,
            criteria: [],
            relations: $relations,
            columns: $columns,
            extra: $extra,
            shouldCache: ! $bypass && ! $isLocked,
            queryType: $queryType,
            recordId: $recordId,
            scope: $this->cacheScope(),
            callback: $callback,
        );
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
    }

    private function isCached(string $method): bool
    {
        return in_array($method, $this->cachedMethods(), true);
    }

    public function fetchAll(
        array $criteria = [],
        array $relations = [],
        array $columns = ['*'],
        bool $paginate = true,
        int $perPage = 25,
    ): Collection|LengthAwarePaginator {
        $isLocked = $this->lockMode !== false;
        $bypass = $this->bypassCache;
        $this->bypassCache = false;
        $pendingScopes = $this->pendingScopes;
        $this->pendingScopes = [];
        $relations = array_values(array_unique(array_merge($relations, $this->pendingRelations)));
        $this->pendingRelations = [];

        /**
         * Paginated results are request-bound (current page lives in the request).
         * Caching them would return page 1 data for every subsequent page request
         * that shares the same criteria. Only cache non-paginated collections.
         */
        $shouldCache = $this->isCached(__FUNCTION__)
            && ! $bypass
            && ! $isLocked
            && ! $paginate;

        $result = $this->cache->remember(
            model: $this->model,
            method: __FUNCTION__,
            criteria: $criteria,
            relations: $relations,
            columns: $columns,
            extra: ['scopes' => $pendingScopes],
            shouldCache: $shouldCache,
            queryType: QueryType::Listing,
            scope: $this->cacheScope(),
            callback: function () use ($criteria, $relations, $columns, $paginate, $perPage, $pendingScopes) {
                /** @var Builder<Model> $query */
                $query = $this->model->newQuery();
                $query->select($columns);

                $this->applyLock($query);

                if ($relations) {
                    $query->with($relations);
                }

                $this->filterApplicator->apply($query, $criteria['filters'] ?? [], $this->searchable());
                $this->sortApplicator->apply($query, $criteria['sort'] ?? [], $this->sortable());
                $this->scopeApplicator->apply($query, $criteria['scopes'] ?? [], $this->resolveScopeable());
                $this->applyScope($query, $pendingScopes);

                return $paginate
                    ? $query->paginate($perPage)
                    : $query->get();
            },
        );

        return $result;
    }

    public function fetchById(
        int|string $id,
        array $relations = [],
        array $columns = ['*'],
    ): Model {
        $isLocked = $this->lockMode !== false;
        $bypass = $this->bypassCache;
        $this->bypassCache = false;
        $pendingScopes = $this->pendingScopes;
        $this->pendingScopes = [];
        $relations = array_values(array_unique(array_merge($relations, $this->pendingRelations)));
        $this->pendingRelations = [];

        $shouldCache = $this->isCached(__FUNCTION__)
            && ! $bypass
            && ! $isLocked;

        $result = $this->cache->remember(
            model: $this->model,
            method: __FUNCTION__,
            criteria: ['id' => $id, 'scopes' => $pendingScopes],
            relations: $relations,
            columns: $columns,
            extra: ['id' => $id, 'scopes' => $pendingScopes],
            shouldCache: $shouldCache,
            queryType: QueryType::Record,
            recordId: $id,
            scope: $this->cacheScope(),
            callback: function () use ($id, $relations, $columns, $pendingScopes) {
                /** @var Builder<Model> $query */
                $query = $this->model->newQuery();
                $query->select($columns);

                $this->applyLock($query);
                if ($relations) {
                    $query->with($relations);
                }

                $this->applyScope($query, $pendingScopes);

                return $query->findOrFail($id);
            },
        );

        return $result;
    }

    public function fetchOneByCriteria(
        array $criteria = [],
        array $relations = [],
        array $columns = ['*'],
    ): Model {
        $shouldCache = $this->isCached(__FUNCTION__) && ! $this->bypassCache;
        $bypass = $this->bypassCache;
        $this->bypassCache = false;
        $pendingScopes = $this->pendingScopes;
        $this->pendingScopes = [];
        $relations = array_values(array_unique(array_merge($relations, $this->pendingRelations)));
        $this->pendingRelations = [];

        $result = $this->cache->remember(
            model: $this->model,
            method: __FUNCTION__,
            criteria: $criteria,
            relations: $relations,
            columns: $columns,
            extra: ['scopes' => $pendingScopes],
            shouldCache: $shouldCache,
            queryType: QueryType::Record,
            scope: $this->cacheScope(),
            callback: function () use ($criteria, $relations, $columns, $pendingScopes) {
                /** @var Builder<Model> $query */
                $query = $this->model->newQuery();
                $query->select($columns);

                if ($relations) {
                    $query->with($relations);
                }

                $this->filterApplicator->apply($query, $criteria['filters'] ?? [], $this->searchable());
                $this->scopeApplicator->apply($query, $criteria['scopes'] ?? [], $this->resolveScopeable());
                $this->applyScope($query, $pendingScopes);

                return $query->firstOrFail();
            },
        );

        return $result;
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<int, string>  $relations
     * @param  array<int, string>  $columns
     * @return Collection<int, Model>
     */
    public function getByCriteria(
        array $criteria = [],
        array $relations = [],
        array $columns = ['*'],
    ): Collection {
        $shouldCache = $this->isCached(__FUNCTION__) && ! $this->bypassCache;
        $bypass = $this->bypassCache;
        $this->bypassCache = false;
        $pendingScopes = $this->pendingScopes;
        $this->pendingScopes = [];
        $relations = array_values(array_unique(array_merge($relations, $this->pendingRelations)));
        $this->pendingRelations = [];

        $result = $this->cache->remember(
            model: $this->model,
            method: __FUNCTION__,
            criteria: $criteria,
            relations: $relations,
            columns: $columns,
            extra: ['scopes' => $pendingScopes],
            shouldCache: $shouldCache,
            queryType: QueryType::Listing,
            scope: $this->cacheScope(),
            callback: function () use ($criteria, $relations, $columns, $pendingScopes) {
                /** @var Builder<Model> $query */
                $query = $this->model->newQuery();
                $query->select($columns);

                $this->applyLock($query);

                if ($relations) {
                    $query->with($relations);
                }

                $this->filterApplicator->apply($query, $criteria['filters'] ?? [], $this->searchable());
                $this->sortApplicator->apply($query, $criteria['sort'] ?? [], $this->sortable());
                $this->scopeApplicator->apply($query, $criteria['scopes'] ?? [], $this->resolveScopeable());
                $this->applyScope($query, $pendingScopes);

                return $query->get();
            },
        );

        return $result;
    }

    /**
     * @param  array<string, mixed>  $criteria
     * @param  array<int, string>  $relations
     * @param  array<int, string>  $columns
     */
    public function firstByCriteria(
        array $criteria = [],
        array $relations = [],
        array $columns = ['*'],
    ): ?Model {
        $shouldCache = $this->isCached(__FUNCTION__) && ! $this->bypassCache;
        $bypass = $this->bypassCache;
        $this->bypassCache = false;
        $pendingScopes = $this->pendingScopes;
        $this->pendingScopes = [];
        $relations = array_values(array_unique(array_merge($relations, $this->pendingRelations)));
        $this->pendingRelations = [];

        $result = $this->cache->remember(
            model: $this->model,
            method: __FUNCTION__,
            criteria: $criteria,
            relations: $relations,
            columns: $columns,
            extra: ['scopes' => $pendingScopes],
            shouldCache: $shouldCache,
            queryType: QueryType::Record,
            scope: $this->cacheScope(),
            callback: function () use ($criteria, $relations, $columns, $pendingScopes) {
                /** @var Builder<Model> $query */
                $query = $this->model->newQuery();
                $query->select($columns);

                $this->applyLock($query);

                if ($relations) {
                    $query->with($relations);
                }

                $this->filterApplicator->apply($query, $criteria['filters'] ?? [], $this->searchable());
                $this->sortApplicator->apply($query, $criteria['sort'] ?? [], $this->sortable());
                $this->scopeApplicator->apply($query, $criteria['scopes'] ?? [], $this->resolveScopeable());
                $this->applyScope($query, $pendingScopes);

                return $query->first();
            },
        );

        return $result;
    }

    public function count(array $criteria = []): int
    {
        $shouldCache = $this->isCached(__FUNCTION__)
            && ! $this->bypassCache;

        $bypass = $this->bypassCache;
        $this->bypassCache = false;
        $pendingScopes = $this->pendingScopes;
        $this->pendingScopes = [];

        $result = $this->cache->remember(
            model: $this->model,
            method: __FUNCTION__,
            criteria: $criteria,
            relations: [],
            columns: ['*'],
            extra: ['scopes' => $pendingScopes],
            shouldCache: $shouldCache,
            queryType: QueryType::Listing,
            scope: $this->cacheScope(),
            callback: function () use ($criteria, $pendingScopes) {
                /** @var Builder<Model> $query */
                $query = $this->model->newQuery();

                $this->filterApplicator->apply($query, $criteria['filters'] ?? [], $this->searchable());
                $this->scopeApplicator->apply($query, $criteria['scopes'] ?? [], $this->resolveScopeable());
                $this->applyScope($query, $pendingScopes);

                return $query->count();
            },
        );

        return $result;
    }

    public function exists(array $criteria = []): bool
    {
        $shouldCache = $this->isCached(__FUNCTION__)
            && ! $this->bypassCache;
        $bypass = $this->bypassCache;
        $this->bypassCache = false;
        $pendingScopes = $this->pendingScopes;
        $this->pendingScopes = [];

        $result = $this->cache->remember(
            model: $this->model,
            method: __FUNCTION__,
            criteria: $criteria,
            relations: [],
            columns: ['*'],
            extra: ['scopes' => $pendingScopes],
            shouldCache: $shouldCache,
            queryType: QueryType::Listing,
            scope: $this->cacheScope(),
            callback: function () use ($criteria, $pendingScopes) {
                /** @var Builder<Model> $query */
                $query = $this->model->newQuery();

                $this->filterApplicator->apply($query, $criteria['filters'] ?? [], $this->searchable());
                $this->scopeApplicator->apply($query, $criteria['scopes'] ?? [], $this->resolveScopeable());
                $this->applyScope($query, $pendingScopes);

                return $query->exists();
            },
        );

        return $result;
    }

    public function sync(Model $model, array $relations): Model
    {
        $modelClass = $model::class;
        $repositoryModelClass = $this->model::class;
        throw_if(
            condition: is_subclass_of($modelClass, $repositoryModelClass) === false,
            exception: new InvalidArgumentException("Model class [{$modelClass}] does not match repository model [{$repositoryModelClass}]."),
        );

        foreach ($relations as $relation => $relatedIds) {
            throw_if(
                condition: ! method_exists($model, $relation),
                exception: new InvalidArgumentException("Relation [{$relation}] does not exist on model [{$modelClass}].", Response::HTTP_BAD_REQUEST),
            );

            $model->{$relation}()->sync($relatedIds);
        }

        $this->cache->flushAll($model, $this->cacheScope());

        // fresh() returns null if the record was deleted concurrently between
        // the sync above and this re-fetch — fall back to the in-memory instance
        // (already carries the synced relations) rather than breaking the
        // non-nullable return contract for a race this method can't prevent.
        return $model->fresh() ?? $model;
    }

    public function create(array $attributes): Model
    {
        $model = $this->model->newQuery()->create($attributes);

        $this->cache->flushAll($model, $this->cacheScope());

        return $model;
    }

    /**
     * ┌─────────────────────────────────────────────────────────────────────────┐
     * │ $quiet — SAME MECHANISM AS ELOQUENT'S OWN Model::updateQuietly()        │
     * │                                                                         │
     * │ $quiet: true fills and saves via the model's own updateQuietly(),       │
     * │ which runs inside Model::withoutEvents() — no updating/updated events,  │
     * │ no observers, no broadcasts fire. Cache is correspondingly NOT          │
     * │ flushed here either, since nothing in this call informs the cache       │
     * │ layer that anything changed.                                            │
     * │                                                                         │
     * │ ONLY pass true when the updated attributes are:                         │
     * │   1. NOT present in any cached read (fetchAll/fetchById output, a       │
     * │      BaseResource field, a service-level cache derived from this        │
     * │      model), and                                                        │
     * │   2. NOT something an observer, listener, or broadcast reacts to.       │
     * │                                                                         │
     * │ Typical case: high-frequency telemetry nobody reads through the cache   │
     * │ layer — last_seen_at, login_count, a heartbeat timestamp. Getting this  │
     * │ wrong is a correctness bug, not a performance trade-off: fetchById()/   │
     * │ fetchAll() keep serving the pre-update value indefinitely. When in      │
     * │ doubt, leave $quiet false.                                              │
     * │                                                                         │
     * │   $this->userRepository->update(                                        │
     * │       $id, ['last_seen_at' => now()], quiet: true,                      │
     * │   );                                                                    │
     * └─────────────────────────────────────────────────────────────────────────┘
     */
    public function update(int|string $id, array $attributes, bool $quiet = false): Model
    {
        $model = $this->model->newQuery()->findOrFail($id);

        if ($quiet) {
            $model->updateQuietly($attributes);

            return $model;
        }

        $model->update($attributes);

        $this->cache->flushRecord($model, $id, $this->cacheScope());

        return $model;
    }

    /**
     * Update a record only if it still matches $conditions — optimistic locking
     * in a single atomic SQL statement, instead of a read-then-write race.
     *
     * Throws StaleDataException if $conditions no longer match (someone else
     * wrote to this record first). A zero affected-row count that still matches
     * $conditions is a genuine no-op (data was already identical) and returns
     * normally.
     *
     * CAVEAT — bypasses Eloquent model events: the update runs as a query-builder
     * mass update (->where($conditions)->update($attributes)), not $model->save().
     * Eloquent never fires updating/updated for query-builder mass updates, so any
     * BaseObserver hook registered on this model (search-index sync, audit
     * logging, anything wired to updating/updated) will NOT run for an atomic
     * update. Cache invalidation is unaffected — it's the explicit flushRecord()
     * call below, not an event listener. If an observer-driven side effect must
     * run on every update including atomic ones, put it in the calling service.
     */
    public function updateAtomic(int|string $id, array $attributes, array $conditions): Model
    {
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

        $this->cache->flushRecord($model, $id, $this->cacheScope());

        $model->refresh();

        return $model;
    }

    public function delete(int|string $id): bool
    {
        $model = $this->model->newQuery()->findOrFail($id);
        $result = (bool) $model->delete();

        $this->cache->flushRecord($model, $id, $this->cacheScope());

        return $result;
    }
}
