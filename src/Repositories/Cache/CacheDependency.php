<?php

namespace CoreFoundation\Repositories\Cache;

use CoreFoundation\Entities\BaseModel;

/**
 * CacheDependency
 *
 * Builds repository-compatible dependency tag strings for service-layer caching.
 *
 * This is the bridge between the repository and service cache layers.
 * When a service declares its cached result depends on a model entity,
 * it uses the SAME tag format the repository uses — so when the repository
 * observer flushes that tag, the service cache is automatically invalidated too.
 *
 * Zero coupling between layers. The service doesn't know about the repository.
 * The repository doesn't know about the service. Tags are the shared contract.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHEN TO USE WHICH DEPENDENCY TYPE                                           │
 * │                                                                             │
 * │ onListing($model, $scope)                                                   │
 * │   Use when: your result depends on an unknown set of records from a model.  │
 * │   Example:  getRecommendations() — depends on the full product catalog.     │
 * │   Invalidated: on any create, update, delete of that model+scope.           │
 * │                                                                             │
 * │ onRecord($model, $id, $scope)                                               │
 * │   Use when: your result depends on specific known record IDs.               │
 * │   Example:  calculateBundlePrice([1,2,3]) — only depends on products 1,2,3. │
 * │   Invalidated: ONLY when one of those specific records changes.             │
 * │   Product 456 update does NOT invalidate this cache.                        │
 * │                                                                             │
 * │ onRecords($model, $ids, $scope)                                             │
 * │   Shorthand for declaring multiple record dependencies at once.             │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ EXAMPLE — service method with mixed dependencies                            │
 * │                                                                             │
 * │   public function calculateShippingOptions(int $tenantId, array $productIds)│
 * │   {                                                                         │
 * │       $scope = new TenantCacheScope($tenantId);                             │
 * │                                                                             │
 * │       return $this->rememberWithDependencies(                               │
 * │           key: "shipping:{$tenantId}:" . implode(',', $productIds),         │
 * │           dependencies: [                                                   │
 * │               // Depends on specific products (weight, dimensions)          │
 * │               ...CacheDependency::onRecords($this->product, $productIds, $scope), │
 * │               // Also depends on shipping zone configuration (listing)      │
 * │               CacheDependency::onListing($this->shippingZone, $scope),     │
 * │           ],                                                                │
 * │           ttl: 1800, // also expire after 30 min (carrier rates change)    │
 * │           callback: fn() => $this->carrierApi->getOptions($productIds),    │
 * │       );                                                                    │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
final class CacheDependency
{
    /**
     * Declare dependency on ALL records of a model within a scope.
     *
     * The cached result will be invalidated when any record of this model
     * is created, updated, or deleted within the scope.
     *
     * Use when: result depends on the full model listing (unknown record set).
     * Examples: search results, recommendations, aggregate counts, sorted lists.
     *
     * @param  BaseModel  $model  Schema reference — used to derive the table name
     * @param  CacheScope|null  $scope  Isolation boundary; null = global
     */
    public static function onListing(BaseModel $model, ?CacheScope $scope = null): string
    {
        $base = sprintf('%s:listing', $model->getTable());

        return $scope
            ? sprintf('%s:%s', $scope->prefix(), $base)
            : $base;
    }

    /**
     * Declare dependency on a SPECIFIC record.
     *
     * The cached result will be invalidated only when this specific record
     * is updated or deleted. Other records changing have no effect.
     *
     * Use when: result is computed from known record IDs.
     * Examples: bundle price for [product 1, 2, 3], product detail enrichment.
     *
     * @param  int|string  $id  The record's primary key
     * @param  CacheScope|null  $scope  Must match the scope used at repository write time
     */
    public static function onRecord(BaseModel $model, int|string $id, ?CacheScope $scope = null): string
    {
        $base = sprintf('%s:record:%s', $model->getTable(), $id);

        return $scope
            ? sprintf('%s:%s', $scope->prefix(), $base)
            : $base;
    }

    /**
     * Declare dependency on multiple specific records.
     *
     * Returns one dependency tag per record ID.
     * The result is invalidated when ANY one of the specified records changes.
     * Other records (e.g., product 456) changing have no effect.
     *
     * Use when: result is computed from multiple known record IDs.
     *
     * @param  array<int|string>  $ids  The records' primary keys
     * @return array<string>
     */
    public static function onRecords(BaseModel $model, array $ids, ?CacheScope $scope = null): array
    {
        return array_values(array_map(
            static fn (int|string $id) => self::onRecord($model, $id, $scope),
            $ids,
        ));
    }
}
