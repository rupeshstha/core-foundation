<?php

namespace CoreFoundation\Repositories\Cache;

use CoreFoundation\Entities\BaseModel;

/**
 * CacheDependency
 *
 * Builds repository-compatible dependency tag strings for service-layer caching.
 *
 * This is the bridge between the Repository layer (which owns the data) and
 * the Service layer (which computes expensive results).
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
     * Dependency on a listing tier (any change to the model in scope busts it).
     */
    public static function onModel(BaseModel $model, ?CacheScope $scope = null): string
    {
        $prefix = $scope ? "{$scope->prefix()}:" : '';

        return "{$prefix}{$model->getTable()}:listing";
    }

    /**
     * Dependency on a specific record tier (only changes to this ID bust it).
     */
    public static function onRecord(BaseModel $model, int|string $id, ?CacheScope $scope = null): string
    {
        $prefix = $scope ? "{$scope->prefix()}:" : '';

        return "{$prefix}{$model->getTable()}:record:{$id}";
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
