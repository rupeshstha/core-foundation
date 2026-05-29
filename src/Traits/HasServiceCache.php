<?php

namespace CoreFoundation\Traits;

use Closure;

/**
 * HasServiceCache
 *
 * Service-layer cache with explicit dependency declarations.
 *
 * Extends HasCacheable with a dependency-aware API designed for services that
 * cache results derived from external APIs, complex computations, or multi-step
 * data mappings where automatic observer-based invalidation is not sufficient.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ HOW IT CONNECTS TO THE REPOSITORY LAYER                                     │
 * │                                                                             │
 * │ Repository and service caches share the same Redis tag namespace.           │
 * │ When the repository observer flushes tag "tenant:5:products:listing",       │
 * │ ALL cache entries tagged with that value are invalidated — including        │
 * │ service results that declared that tag as a dependency.                     │
 * │                                                                             │
 * │ Zero coupling. No service-to-repository reference needed.                   │
 * │ Tags are the shared contract.                                               │
 * │                                                                             │
 * │ USE CacheDependency to build dependency tags that match repository format:  │
 * │   CacheDependency::onListing($product, $scope)   — listing dependency       │
 * │   CacheDependency::onRecord($product, $id, $scope) — record dependency      │
 * │   CacheDependency::onRecords($product, $ids, $scope) — multiple records     │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ TWO USAGE PATTERNS                                                          │
 * │                                                                             │
 * │ PATTERN A — listing dependency (invalidated when any record changes)        │
 * │                                                                             │
 * │   public function getRecommendations(int $userId): array                    │
 * │   {                                                                         │
 * │       return $this->rememberWithDependencies(                               │
 * │           key: "recommendations:tenant:{$tenantId}:user:{$userId}",         │
 * │           dependencies: [CacheDependency::onListing($this->product, $scope)],│
 * │           ttl: 3600, // external API — also expire after 1h                 │
 * │           callback: fn() => $this->mlApi->getRecommendations($userId),     │
 * │       );                                                                    │
 * │   }                                                                         │
 * │                                                                             │
 * │ PATTERN B — record dependency (invalidated only when those records change)  │
 * │                                                                             │
 * │   public function calculateBundlePrice(array $productIds): BundlePrice      │
 * │   {                                                                         │
 * │       return $this->rememberWithDependencies(                               │
 * │           key: "bundle:{$tenantId}:" . implode(',', sort($productIds)),     │
 * │           dependencies: CacheDependency::onRecords($this->product, $productIds, $scope), │
 * │           // no TTL — only invalidate when specific products change          │
 * │           callback: fn() => $this->computePrice($productIds),              │
 * │       );                                                                    │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ THIRD-PARTY API CACHING GUIDELINES                                          │
 * │                                                                             │
 * │ Always set a TTL when caching third-party API results:                      │
 * │   - Third-party data changes independently of your models                  │
 * │   - Tags only invalidate when YOUR data changes, not the external service   │
 * │   - TTL is your freshness guarantee for the external data component        │
 * │                                                                             │
 * │ The effective cache lifetime is min(TTL, next dependency flush).            │
 * │   Model changes bust the cache early — TTL catches everything else.        │
 * │                                                                             │
 * │ Recommended TTLs:                                                           │
 * │   Pricing / availability  →  300–900s  (changes frequently)               │
 * │   Shipping rates          →  1800s     (changes occasionally)              │
 * │   Recommendations         →  3600s     (slowly evolving)                  │
 * │   Tax rates               →  86400s    (rarely changes)                   │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait HasServiceCache
{
    use HasCacheable;

    /**
     * Cache a service result with explicit dependency declarations.
     *
     * The result is invalidated when ANY of the dependency tags are flushed.
     * Dependencies are typically repository cache tags built via CacheDependency.
     *
     * With TTL:    result expires at min(ttl_seconds, next_dependency_flush).
     * Without TTL: result lives until a dependency is flushed. Use for:
     *              - Pure computations with no external data component.
     *              - Computations where model change = always stale.
     *
     * @param  string  $key  Unique, namespaced cache key. Include tenant/user IDs.
     * @param  array<string>  $dependencies  Repository-compatible dependency tags.
     *                                      Build via CacheDependency::on*().
     *                                      Must not be empty — throws if passed empty array.
     * @param  Closure(): mixed  $callback  The expensive operation to cache.
     * @param  int|null  $ttl  Seconds until expiry. null = cache forever (dep-only).
     *
     * @throws \InvalidArgumentException  When dependencies is empty (use cacheForever instead).
     */
    final protected function rememberWithDependencies(
        string $key,
        array $dependencies,
        Closure $callback,
        ?int $ttl = null,
    ): mixed {
        if ($dependencies === []) {
            throw new \InvalidArgumentException(
                static::class . '::rememberWithDependencies() requires at least one dependency. '
                . 'Use HasCacheable::cacheForever() for non-dependency caching, '
                . 'or declare the model/entity this result depends on via CacheDependency.',
            );
        }

        return $ttl !== null
            ? $this->cacheTtl($dependencies, $key, $ttl, $callback)
            : $this->cacheForever($dependencies, $key, $callback);
    }

    /**
     * Manually invalidate service cache for specific dependencies.
     *
     * Use when you know dependencies have changed outside of Eloquent model events
     * (e.g., a config change, a manual admin action, an external webhook).
     *
     * @param  non-empty-array<string>  $dependencies  The tags to flush.
     */
    final protected function invalidateServiceCache(array $dependencies): void
    {
        $this->bustCache($dependencies);
    }
}
