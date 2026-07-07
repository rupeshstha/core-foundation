<?php

namespace CoreFoundation\Repositories\Cache;

use CoreFoundation\Entities\BaseModel;

/**
 * CacheDependency
 *
 * Builds repository-compatible dependency tag strings for service-layer caching.
 *
 * This is the bridge between the Repository layer (which owns the data) and
 * the Service layer (which computes expensive results). Delegates to
 * CacheKeyBuilder for the actual tag format — never re-derive the string here,
 * or this and the repository's own tags will silently drift apart.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE                                                                       │
 * │                                                                             │
 * │   public function calculateShippingOptions(int $tenantId, array $productIds)│
 * │   {                                                                         │
 * │       $scope = new PrefixCacheScope("tenant:{$tenantId}");                 │
 * │                                                                             │
 * │       return $this->rememberWithDependencies(                               │
 * │           key: "shipping:{$tenantId}:" . implode(',', $productIds),          │
 * │           dependencies: [                                                   │
 * │               // Bust if any of these products are updated                  │
 * │               ...CacheDependency::onRecords($product, $productIds, $scope),  │
 * │           ],                                                                │
 * │           callback: fn() => $this->compute($productIds)                     │
 * │       );                                                                    │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
final class CacheDependency
{
    /**
     * Dependency on the listing tier (any change to the model in scope busts it).
     */
    public static function onListing(BaseModel $model, ?CacheScope $scope = null): string
    {
        return (new CacheKeyBuilder)->buildListingTag($model, $scope);
    }

    /**
     * Dependency on a specific record tier (only changes to this ID bust it).
     */
    public static function onRecord(BaseModel $model, int|string $id, ?CacheScope $scope = null): string
    {
        return (new CacheKeyBuilder)->buildRecordTag($model, $id, $scope);
    }

    /**
     * Dependency on a collection of specific records.
     *
     * @param  list<int|string>  $ids
     * @return list<string>
     */
    public static function onRecords(BaseModel $model, array $ids, ?CacheScope $scope = null): array
    {
        return array_values(array_map(
            static fn (int|string $id) => self::onRecord($model, $id, $scope),
            $ids,
        ));
    }
}
