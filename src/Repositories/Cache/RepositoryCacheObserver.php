<?php

namespace CoreFoundation\Repositories\Cache;

use CoreFoundation\Entities\BaseModel;

/**
 * RepositoryCacheObserver
 *
 * Wires Eloquent model events to scope-aware cache invalidation automatically.
 *
 * The Eloquent event delivers the ACTUAL saved model instance — this is where
 * the tenant_id (or any scope discriminator) is read from. No TenantContext
 * service needed here; the model itself carries the scope.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ INVALIDATION STRATEGY                                                       │
 * │                                                                             │
 * │ created  → flushModel  (new record changes listing results)                 │
 * │ updated  → flushRecord (this record + listing, other records stay warm)     │
 * │ deleted  → flushRecord (record is gone; bust its cache + listing)           │
 * │ restored → flushModel  (soft-delete restore changes listing results)        │
 * │                                                                             │
 * │ SCOPE EXTRACTION                                                            │
 * │                                                                             │
 * │ The scope is derived automatically from the model's 'tenant_id' attribute  │
 * │ when present. Models without 'tenant_id' use unscoped (global) invalidation.│
 * │                                                                             │
 * │ To use a different scope attribute, override scopeFromModel() in a         │
 * │ subclass and register that subclass as the observer instead.                │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ REGISTRATION — in your module's ServiceProvider::boot():                    │
 * │                                                                             │
 * │   Product::observe(RepositoryCacheObserver::class);                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
class RepositoryCacheObserver
{
    public function __construct(
        private readonly RepositoryCache $cache,
    ) {}

    /**
     * Handle the "created" event.
     * New records only affect listings; no individual record cache exists yet.
     */
    public function created(BaseModel $model): void
    {
        $this->cache->flushModel($model, $this->scopeFromModel($model));
    }

    /**
     * Handle the "updated" event.
     * Must flush both the record (stale data) and listings (may have changed position/filtered out).
     */
    public function updated(BaseModel $model): void
    {
        $this->cache->flushRecord($model, $model->getKey(), $this->scopeFromModel($model));
    }

    /**
     * Handle the "deleted" event.
     * Must flush both the record and listings.
     */
    public function deleted(BaseModel $model): void
    {
        $this->cache->flushRecord($model, $model->getKey(), $this->scopeFromModel($model));
    }

    /**
     * Handle the "restored" event.
     * Restored records affect listings (they reappear); individual record cache is likely already empty.
     */
    public function restored(BaseModel $model): void
    {
        $this->cache->flushModel($model, $this->scopeFromModel($model));
    }

    // =========================================================================
    // Extension point
    // =========================================================================

    /**
     * Derive a CacheScope from a model instance.
     *
     * Override in a subclass to use a different attribute or scope type.
     *
     * Default: reads 'tenant_id' — returns TenantCacheScope when present,
     * null (global invalidation) when the model has no tenant_id.
     */
    protected function scopeFromModel(BaseModel $model): ?CacheScope
    {
        $tenantId = $model->getAttribute('tenant_id');

        return $tenantId !== null
            ? new TenantCacheScope($tenantId)
            : null;
    }
}
