<?php

namespace CoreFoundation\Repositories\Cache;

use CoreFoundation\Entities\BaseModel;

/**
 * RepositoryCacheObserver
 *
 * Wires Eloquent model events to cache invalidation automatically.
 * The default wiring — repositories can override by calling cache methods directly.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ SETUP — register in your model's ServiceProvider::boot():                   │
 * │                                                                             │
 * │   Order::observe(RepositoryCacheObserver::class);                           │
 * │                                                                             │
 * │ Or register globally for all BaseModel subclasses in                        │
 * │ CoreFoundationServiceProvider::boot().                                      │
 * │                                                                             │
 * │ The repository can still override by calling:                               │
 * │   $this->cache->flushModel($this->model);   // after create/delete         │
 * │   $this->cache->flushRecord($model, $id);   // after update                │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ INVALIDATION STRATEGY                                                       │
 * │                                                                             │
 * │ created  → tag flush (new record changes fetchAll results)                  │
 * │ updated  → granular flush (only this record's cache is invalidated)         │
 * │ deleted  → tag flush (record no longer exists, all list queries stale)      │
 * │ restored → tag flush (soft-delete restore changes fetchAll results)         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
final class RepositoryCacheObserver
{
    public function __construct(
        private readonly RepositoryCache $cache,
    ) {}

    public function created(BaseModel $model): void
    {
        $this->cache->flushModel($model);
    }

    public function updated(BaseModel $model): void
    {
        $this->cache->flushRecord($model, $model->getKey());
    }

    public function deleted(BaseModel $model): void
    {
        $this->cache->flushModel($model);
    }

    public function restored(BaseModel $model): void
    {
        $this->cache->flushModel($model);
    }
}
