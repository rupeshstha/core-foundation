<?php

namespace CoreFoundation\Repositories\Cache;

use Closure;
use Illuminate\Support\Facades\Log;
use CoreFoundation\Traits\HasCacheable;
use Illuminate\Database\Eloquent\Model;

final class RepositoryCache
{
    use HasCacheable;

    private readonly bool $globallyEnabled;

    private readonly int $ttl;

    public function __construct(
        private readonly CacheKeyBuilder $keyBuilder,
        private readonly RelationTagResolver $tagResolver,
        private readonly CacheBustCollector $bustCollector,
    ) {
        $this->globallyEnabled = (bool) config('core-foundation.cache.global', true);
        $this->ttl = (int) config('core-foundation.cache.cache_ttl', 3600);
    }

    /**
     * Execute $callback through the cache layer.
     *
     * Bypasses the cache (runs callback directly) when:
     *   - Cache is globally disabled via config
     *   - $shouldCache is false (paginated, locked, or caller-bypassed)
     *
     * @param  QueryType  $queryType  Listing or Record — determines tag tier
     * @param  int|string|null  $recordId  Required when $queryType is Record
     * @param  CacheScope|null  $scope  Isolation boundary; null = global (no isolation)
     */
    public function remember(
        Model $model,
        string $method,
        array $criteria,
        array $relations,
        array $columns,
        array $extra,
        bool $shouldCache,
        Closure $callback,
        QueryType $queryType = QueryType::Listing,
        int|string|null $recordId = null,
        ?CacheScope $scope = null,
    ): mixed {
        if (! $this->globallyEnabled || ! $shouldCache) {
            return $callback();
        }

        $key = $this->keyBuilder->build($model, $method, $criteria, $relations, $columns, $extra, $scope);
        $tags = $this->buildTags($model, $relations, $queryType, $recordId, $scope);

        return $this->cacheTtl($tags, $key, $this->ttl, $callback);
    }

    /**
     * Flush the LISTING cache for this model within the scope.
     *
     * Call on: create, delete (via observer).
     * Does NOT flush record-tier caches — those stay warm.
     */
    public function flushModel(Model $model, ?CacheScope $scope = null): void
    {
        $tag = $this->keyBuilder->buildListingTag($model, $scope);

        $this->bustCache([$tag]);
        $this->bustCollector->record([$tag]);

        Log::info('[Cache] Listing flushed', [
            'model' => $model::class,
            'scope' => $scope?->prefix() ?? 'global',
            'tag' => $tag,
        ]);
    }

    /**
     * Flush RECORD + LISTING cache for a specific record.
     *
     * Call on: update (via observer or updateAtomic).
     *
     * Both tiers are flushed in a single Redis call:
     *   Record tag  → busts fetchById(123) for this scope
     *   Listing tag → busts fetchAll results that may include the stale record
     *
     * What stays warm after flushing record 123 in tenant 5:
     *    tenant:5:products:record:456   (other records, same tenant)
     *    tenant:1:products:record:123   (same record, different tenant)
     *    tenant:1:products:listing      (other tenant's listings)
     */
    public function flushRecord(Model $model, int|string $id, ?CacheScope $scope = null): void
    {
        $recordTag = $this->keyBuilder->buildRecordTag($model, $id, $scope);
        $listingTag = $this->keyBuilder->buildListingTag($model, $scope);

        $this->bustCache([$recordTag]);
        $this->bustCache([$listingTag]);
        $this->bustCollector->record([$recordTag, $listingTag]);

        Log::info('[Cache] Record flushed', [
            'model' => $model::class,
            'id' => $id,
            'scope' => $scope?->prefix() ?? 'global',
            'record_tag' => $recordTag,
            'listing_tag' => $listingTag,
        ]);
    }

    /**
     * Flush ALL cache for this model within scope — all tiers, all records.
     *
     * Use for: bulk imports, mass updates, operations that bypass observers.
     * More destructive than flushRecord — prefer that for single writes.
     */
    public function flushAll(Model $model, ?CacheScope $scope = null): void
    {
        $baseTag = $scope
            ? sprintf('%s:%s', $scope->prefix(), $model->getTable())
            : $model->getTable();

        $this->bustCache([$baseTag]);
        $this->bustCollector->record([$baseTag]);

        Log::info('[Cache] Full flush', [
            'model' => $model::class,
            'scope' => $scope?->prefix() ?? 'global',
            'tag' => $baseTag,
        ]);
    }

    /**
     * Build the tag set for a cached query.
     *
     * Tags stored on the entry determine which flush operations invalidate it:
     *   baseTag     — flushAll() busts everything for this model+scope
     *   primaryTag  — flushModel()/flushRecord() busts by tier
     *   relationTags — a write to a loaded related table also busts this entry
     *
     * @return non-empty-array<string>
     */
    private function buildTags(
        Model $model,
        array $relations,
        QueryType $queryType,
        int|string|null $recordId,
        ?CacheScope $scope,
    ): array {
        $baseTag = $scope
            ? sprintf('%s:%s', $scope->prefix(), $model->getTable())
            : $model->getTable();

        $primaryTag = match ($queryType) {
            QueryType::Listing => $this->keyBuilder->buildListingTag($model, $scope),
            QueryType::Record => $recordId !== null
                ? $this->keyBuilder->buildRecordTag($model, $recordId, $scope)
                : $this->keyBuilder->buildListingTag($model, $scope),
        };

        $relationTags = $this->tagResolver->resolve($relations);

        return array_unique([$baseTag, $primaryTag, ...$relationTags]);
    }
}
