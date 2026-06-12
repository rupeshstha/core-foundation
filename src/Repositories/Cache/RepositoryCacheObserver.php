<?php

namespace CoreFoundation\Repositories\Cache;

use Closure;
use Illuminate\Database\Eloquent\Model;

/**
 * RepositoryCacheObserver
 *
 * Wires Eloquent model events to scope-aware cache invalidation automatically.
 */
class RepositoryCacheObserver
{
    /** @var Closure(Model): ?CacheScope|null */
    protected static ?Closure $scopeResolver = null;

    public function __construct(protected RepositoryCache $cache) {}

    /**
     * Set a global resolver to determine the CacheScope for a given model.
     *
     * Example:
     *   RepositoryCacheObserver::resolveScopeUsing(fn($m) => $m->tenant_id ? new PrefixCacheScope("tenant:{$m->tenant_id}") : null);
     */
    public static function resolveScopeUsing(Closure $resolver): void
    {
        static::$scopeResolver = $resolver;
    }

    public function created(Model $model): void
    {
        $this->cache->flushModel($model, $this->getScope($model));
    }

    public function updated(Model $model): void
    {
        $this->cache->flushRecord($model, $model->getKey(), $this->getScope($model));
    }

    public function deleted(Model $model): void
    {
        $this->cache->flushRecord($model, $model->getKey(), $this->getScope($model));
    }

    public function restored(Model $model): void
    {
        $this->cache->flushModel($model, $this->getScope($model));
    }

    protected function getScope(Model $model): ?CacheScope
    {
        if (static::$scopeResolver) {
            return (static::$scopeResolver)($model);
        }

        return null;
    }
}
