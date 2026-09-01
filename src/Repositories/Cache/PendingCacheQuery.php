<?php

namespace CoreFoundation\Repositories\Cache;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Traits\Conditionable;

/**
 * PendingCacheQuery
 *
 * Fluent builder for a cached repository read, returned by
 * BaseRepository::cacheQuery() — same shape as Illuminate\Http\Client\PendingRequest
 * or Illuminate\Mail\PendingMail: a few optional configuration calls, one
 * terminal action. Composes Conditionable for the same reason PendingRequest
 * does — when()/unless() to configure conditionally without breaking the chain.
 *
 * This is the ONE place $this->cache->remember() is ever called — every one
 * of BaseRepository's own fetchAll()/fetchById()/fetchOneByCriteria()/
 * getByCriteria()/firstByCriteria()/count()/exists() builds one of these and
 * calls remember() on it, same as a brand new custom method would.
 *
 *   // Listing-tier, no extra key material needed.
 *   public function listPublic(): Collection
 *   {
 *       return $this->cacheQuery(__FUNCTION__)
 *           ->with('entitlements')
 *           ->remember(fn () => $this->query()->where('is_public', true)->with('entitlements')->get());
 *   }
 *
 *   // Record-tier keyed by an explicit argument — need not be this model's
 *   // own primary key. Lets a future flushRecordCache($shopId) target just
 *   // this shop instead of flushAllCache() busting every shop's cache.
 *   public function getBalance(int $shopId): int
 *   {
 *       return $this->cacheQuery(__FUNCTION__)
 *           ->withKey(['shop_id' => $shopId])
 *           ->asRecord($shopId)
 *           ->remember(fn () => (int) $this->query()->where('shop_id', $shopId)->sum('amount_cents'));
 *   }
 *
 *   // when()/unless(), same as any other Laravel fluent builder — dontCache()
 *   // is how a method adds a gate cacheQuery() doesn't apply automatically
 *   // (cacheQuery() already covers withoutCache() and an active pessimistic
 *   // lock; anything extra, e.g. a config-driven allowlist, is opt-in here).
 *   return $this->cacheQuery(__FUNCTION__)
 *       ->when($paginated, fn (PendingCacheQuery $query) => $query->dontCache())
 *       ->remember(fn () => ...);
 *
 * Never constructed directly — only reachable via the protected
 * BaseRepository::cacheQuery(), same encapsulation as query(): Builder.
 */
final class PendingCacheQuery
{
    use Conditionable;

    /** @var array<string, mixed> */
    private array $criteria = [];

    /** @var array<string> */
    private array $columns = ['*'];

    /** @var array<string, mixed> */
    private array $extra = [];

    /** @var array<string> */
    private array $relations = [];

    private QueryType $queryType = QueryType::Listing;

    private int|string|null $recordId = null;

    public function __construct(
        private readonly RepositoryCache $cache,
        private readonly Model $model,
        private readonly string $method,
        private readonly ?CacheScope $scope,
        private bool $shouldCache,
    ) {}

    /**
     * Filter/sort/scope criteria, threaded through FilterApplicator/
     * SortApplicator/ScopeApplicator inside the callback and folded into the
     * cache key here — same $criteria shape fetchAll()/fetchById() accept.
     *
     * @param  array<string, mixed>  $criteria
     */
    public function criteria(array $criteria): static
    {
        $this->criteria = $criteria;

        return $this;
    }

    /**
     * Selected columns — part of the cache key, same as fetchAll()/fetchById().
     * Most custom methods have nothing to vary here; defaults to ['*'].
     *
     * @param  array<string>  $columns
     */
    public function columns(array $columns): static
    {
        $this->columns = $columns;

        return $this;
    }

    /**
     * Extra discriminators baked into the cache key — anything the method's
     * own arguments contribute, so two different calls don't collide.
     *
     *   ->withKey(['shop_id' => $shopId])
     *
     * @param  array<string, mixed>  $extra
     */
    public function withKey(array $extra): static
    {
        $this->extra = $extra;

        return $this;
    }

    /**
     * Eager-loaded relation names — same shape as BaseRepository::with().
     * Feeds relation-based invalidation too: a write to an eager-loaded
     * related model also busts this cache entry, not just key uniqueness.
     *
     * @param  array<string>|string  $relations
     */
    public function with(array|string $relations): static
    {
        $this->relations = is_array($relations) ? $relations : [$relations];

        return $this;
    }

    /**
     * Tag this entry at the record tier under $id instead of the listing
     * tier. $id does not have to be this model's own primary key — it only
     * has to match the id a corresponding flushRecordCache($id) call uses.
     */
    public function asRecord(int|string $id): static
    {
        $this->queryType = QueryType::Record;
        $this->recordId = $id;

        return $this;
    }

    /**
     * Force this call to skip the cache, on top of whatever cacheQuery()
     * already decided from withoutCache()/lockForUpdate(). Use via when()/
     * unless() for a gate specific to one method — e.g. a config-driven
     * allowlist, or "never cache a paginated result":
     *
     *   ->when($paginated, fn (PendingCacheQuery $query) => $query->dontCache())
     */
    public function dontCache(): static
    {
        $this->shouldCache = false;

        return $this;
    }

    /**
     * Terminal action — run $callback through the cache with everything
     * configured above. Same verb as Cache::remember() on purpose.
     *
     * @param  Closure(): mixed  $callback  Runs only on a cache miss
     */
    public function remember(Closure $callback): mixed
    {
        return $this->cache->remember(
            model: $this->model,
            method: $this->method,
            criteria: $this->criteria,
            relations: $this->relations,
            columns: $this->columns,
            extra: $this->extra,
            shouldCache: $this->shouldCache,
            queryType: $this->queryType,
            recordId: $this->recordId,
            scope: $this->scope,
            callback: $callback,
        );
    }
}
