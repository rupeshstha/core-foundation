<?php

namespace CoreFoundation\Tests\Unit\Repositories;

use CoreFoundation\Repositories\BaseRepository;
use CoreFoundation\Tests\Stubs\Models\TestPost;
use CoreFoundation\Repositories\Cache\PendingCacheQuery;

class TestPostRepository extends BaseRepository
{
    protected function setModel(): string
    {
        return TestPost::class;
    }

    protected function scopeable(): array
    {
        return ['active', 'ofStatus'];
    }

    /**
     * Regression fixture for the documented "named method starting from
     * $this->query()" pattern — proves query() is still reachable from
     * inside a concrete repository after being locked down to protected.
     */
    public function titlesStartingWith(string $prefix): array
    {
        return $this->query()
            ->where('title', 'like', "{$prefix}%")
            ->pluck('title')
            ->all();
    }

    /**
     * Fixture for cacheQuery() — Listing tier, no extra key material needed.
     */
    public function countActive(): int
    {
        return $this->cacheQuery(__FUNCTION__)
            ->remember(fn () => $this->query()->where('status', 'active')->count());
    }

    /**
     * Fixture proving two otherwise-identical Listing-tier cacheQuery()
     * calls on the same repository don't collide on the same cache key —
     * __FUNCTION__ differs, so CacheKeyBuilder's key hash differs too.
     */
    public function countAll(): int
    {
        return $this->cacheQuery(__FUNCTION__)
            ->remember(fn () => $this->query()->count());
    }

    /**
     * Fixture for PendingCacheQuery's Conditionable::when() — switches to
     * the Record tier only when $id is given, without breaking the chain.
     */
    public function countPossiblyById(?int $id): int
    {
        return $this->cacheQuery(__FUNCTION__)
            ->when($id !== null, fn (PendingCacheQuery $query): PendingCacheQuery => $query->withKey(['id' => $id])->asRecord($id))
            ->remember(fn () => $id !== null
                ? $this->query()->where('id', $id)->count()
                : $this->query()->count());
    }

    /**
     * Fixture for PendingCacheQuery's Conditionable::unless() — same
     * mechanism as countPossiblyById() above, inverted condition.
     */
    public function countUnlessId(?int $id): int
    {
        return $this->cacheQuery(__FUNCTION__)
            ->unless($id === null, fn (PendingCacheQuery $query): PendingCacheQuery => $query->withKey(['id' => $id])->asRecord($id))
            ->remember(fn () => $id !== null
                ? $this->query()->where('id', $id)->count()
                : $this->query()->count());
    }

    /**
     * Fixture for cacheQuery()'s criteria() used standalone by a custom
     * method — proves it feeds the cache key independently of withKey(),
     * the same way fetchAll()'s $criteria argument does.
     */
    public function countByStatus(string $status): int
    {
        return $this->cacheQuery(__FUNCTION__)
            ->criteria(['status' => $status])
            ->remember(fn () => $this->query()->where('status', $status)->count());
    }

    /**
     * Fixture for cacheQuery()'s columns() — proves it feeds the cache key
     * on its own. The callback ignores $columns deliberately: this fixture
     * only needs to prove two different column lists produce two different
     * cache entries, not exercise real column-selection SQL (that's
     * Eloquent's own concern).
     */
    public function cachedCountWithColumns(array $columns): int
    {
        return $this->cacheQuery(__FUNCTION__)
            ->columns($columns)
            ->remember(fn () => $this->query()->count());
    }

    /**
     * Fixture for cacheQuery() — Record tier keyed by an explicit argument.
     */
    public function cachedTitle(int $id): ?string
    {
        return $this->cacheQuery(__FUNCTION__)
            ->withKey(['id' => $id])
            ->asRecord($id)
            ->remember(fn () => $this->query()->find($id)?->title);
    }

    /**
     * Fixture proving withoutCache() also applies to a cacheQuery()-cached
     * custom method, not just the built-in fetch* methods.
     */
    public function countActiveFresh(): int
    {
        return $this->withoutCache()->countActive();
    }

    /**
     * Fixture for flushRecordCache() — writes via the query builder directly
     * (bypassing the inherited update()'s own flush) so the only invalidation
     * that happens is the explicit flushRecordCache() call below.
     */
    public function renameAndFlushRecord(int $id, string $title): void
    {
        $this->query()->where('id', $id)->update(['title' => $title]);
        $this->flushRecordCache($id);
    }
}
