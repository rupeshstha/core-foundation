<?php

namespace CoreFoundation\Tests\Unit\Repositories;

use CoreFoundation\Repositories\BaseRepository;
use CoreFoundation\Tests\Stubs\Models\TestPost;
use CoreFoundation\Repositories\Cache\QueryType;

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
     * Fixture for cacheQuery() — Listing tier, no method arguments.
     */
    public function countActive(): int
    {
        return $this->cacheQuery(
            method: __FUNCTION__,
            callback: fn () => $this->query()->where('status', 'active')->count(),
        );
    }

    /**
     * Fixture for cacheQuery() — Record tier keyed by an explicit argument.
     */
    public function cachedTitle(int $id): ?string
    {
        return $this->cacheQuery(
            method: __FUNCTION__,
            callback: fn () => $this->query()->find($id)?->title,
            extra: ['id' => $id],
            queryType: QueryType::Record,
            recordId: $id,
        );
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
