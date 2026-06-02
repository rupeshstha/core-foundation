<?php

namespace CoreFoundation\Repositories\Cache;

use Closure;
use Illuminate\Support\Facades\Log;
use CoreFoundation\Traits\HasCacheable;
use Illuminate\Database\Eloquent\Model;

/**
 * RepositoryCache
 *
 * Tag-based, scope-aware cache manager for repositories.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ TWO-TIER TAG STRATEGY                                                       │
 * │                                                                             │
 * │ LISTING tier  — fetchAll, paginated, search results                         │
 * │   Tag: {scope}:{table}:listing    e.g. tenant:5:products:listing            │
 * │   Flushed when: any record in this model+scope is created/updated/deleted   │
 * │                                                                             │
 * │ RECORD tier   — fetchById, single-record results                            │
 * │   Tag: {scope}:{table}:record:{id}   e.g. tenant:5:products:record:123     │
 * │   Flushed when: THIS specific record is updated or deleted                  │
 * │   Product 456 cache remains warm when product 123 is updated.              │
 * │                                                                             │
 * │ SCOPE isolation                                                             │
 * │   100 tenants × 10,000 products per tenant.                                │
 * │   Update product 123 in tenant 5:                                           │
 * │     Flushes:  tenant:5:products:record:123                                  │
 * │               tenant:5:products:listing                                     │
 * │     Keeps:    tenant:1:products:*   tenant:2:products:*   ... (99 tenants) │
 * │               tenant:5:products:record:124  (9,999 other records)           │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ OCTANE SAFETY                                                               │
 * │                                                                             │
 * │ This class is registered as scoped() — re-created per Octane request.      │
 * │ No static mutable state.                                                    │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
final class RepositoryCache
{
    use HasCacheable;

    private bool $enabled;

    public function __construct(
        private readonly CacheKeyBuilder $keyBuilder,
        private readonly RelationTagResolver $tagResolver,
    ) {
        $this->enabled = (bool) config('core-foundation.cache.global', true);
    }

    // =========================================================================
    // Read — cache-first
    // =========================================================================

    /**
     * Cache a repository query result using scope-aware, tier-targeted tags.
     *
     * @param  QueryType  $queryType  Listing or Record — which tag tier to apply
     * @param  int|string|null  $recordId  Required when $queryType is Record
     * @param  CacheScope|null  $scope  Isolation boundary; null = global (no isolation)
     */
    public function remember(
        Model $model,
        string $method,
        array $filters,
        array $relations,
        array $columns,
        array $extra,
        bool $shouldCache,
        Closure $callback,
        QueryType $queryType = QueryType::Listing,
        int|string|null $recordId = null,
        ?CacheScope $scope = null,
    ): mixed {
        if (! $this->enabled || ! $shouldCache) {
            return $callback();
        }

        $key = $this->keyBuilder->build($model, $method, $filters, $relations, $columns, $extra, $scope);
        $tags = $this->buildTags($model, $relations, $queryType, $recordId, $scope);

        return $this->cacheForever($tags, $key, $callback);
    }

    // =========================================================================
    // Invalidation
    // =========================================================================

    /**
     * Flush the LISTING cache for this model within the scope.
     *
     * Call on: create, delete.
     * Does NOT flush record-tier caches — those stay warm.
     *
     * Example (scoped):   flushes tenant:5:products:listing
     * Example (unscoped): flushes products:listing
     */
    public function flushModel(Model $model, ?CacheScope $scope = null): void
    {
        $tag = $this->keyBuilder->buildListingTag($model, $scope);

        $this->bustCache([$tag]);

        Log::info('[Cache] Listing flushed', [
            'model' => $model::class,
            'scope' => $scope?->prefix() ?? 'global',
            'tag' => $tag,
        ]);
    }

    /**
     * Flush the RECORD cache and LISTING cache for a specific record.
     *
     * Call on: update.
     *
     * Why both tiers?
     *   Record tag  → busts fetchById(123) for this tenant
     *   Listing tag → busts fetchAll results that may include the stale record
     *
     * What stays warm after flushing record 123 in tenant 5:
     *   ✓  tenant:5:products:record:456   (other records, same tenant)
     *   ✓  tenant:1:products:record:123   (same record ID, different tenant)
     *   ✓  tenant:1:products:listing      (other tenant's listings)
     */
    public function flushRecord(Model $model, int|string $id, ?CacheScope $scope = null): void
    {
        $recordTag = $this->keyBuilder->buildRecordTag($model, $id, $scope);
        $listingTag = $this->keyBuilder->buildListingTag($model, $scope);

        $this->bustCache([$recordTag]);
        $this->bustCache([$listingTag]);

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
     *
     * With scope:    only flushes within that scope
     * Without scope: flushes globally across all scopes for this model
     */
    public function flushAll(Model $model, ?CacheScope $scope = null): void
    {
        // The base model tag covers all sub-tags (listing, record:*) for this scope
        $baseTag = $scope
            ? sprintf('%s:%s', $scope->prefix(), $model->getTable())
            : $model->getTable();

        $this->bustCache([$baseTag]);

        Log::info('[Cache] Full flush', [
            'model' => $model::class,
            'scope' => $scope?->prefix() ?? 'global',
            'tag' => $baseTag,
        ]);
    }

    // =========================================================================
    // Control
    // =========================================================================

    public function disable(): self
    {
        $this->enabled = false;

        return $this;
    }

    public function enable(): self
    {
        $this->enabled = true;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Build the tag set for a cached query.
     *
     * The primary tag determines which flush operation invalidates this entry.
     * Relation tags are added so that writes to related models also bust this cache.
     * Relation tags are intentionally NOT scoped — a relation table change is global.
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
        // Base tag: {scope}:{table} — covers all tiers (listing, record:*)
        $baseTag = $scope
            ? sprintf('%s:%s', $scope->prefix(), $model->getTable())
            : $model->getTable();

        $primaryTag = match ($queryType) {
            QueryType::Listing => $this->keyBuilder->buildListingTag($model, $scope),
            QueryType::Record => $recordId !== null
                ? $this->keyBuilder->buildRecordTag($model, $recordId, $scope)
                : $this->keyBuilder->buildListingTag($model, $scope),
        };

        $relationTags = $this->tagResolver->resolveRelationTags($model, $relations);

        return array_unique([$baseTag, $primaryTag, ...$relationTags]);
    }
}
