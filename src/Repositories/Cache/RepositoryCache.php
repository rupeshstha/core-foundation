<?php

namespace CoreFoundation\Repositories\Cache;

use Closure;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use CoreFoundation\Entities\BaseModel;
use CoreFoundation\Traits\HasCacheable;

/**
 * RepositoryCache
 *
 * Replaces RepositoryCacheManager + RepositoryCacheResolver with a single
 * cohesive class. Composes CacheKeyBuilder and RelationTagResolver.
 *
 * Strategy:
 *   READ   → tag-based cache (model table + relation tables as tags)
 *   CREATE → tag flush (all queries for this model invalidated)
 *   DELETE → tag flush (all queries for this model invalidated)
 *   UPDATE → granular flush (only cache entries tagged with this record's ID)
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ OCTANE SAFETY                                                               │
 * │                                                                             │
 * │ This class is scoped (re-created per request) via ServiceProvider.          │
 * │ No static mutable state — only RelationTagResolver uses static cache,       │
 * │ which is safe because relation definitions are immutable at runtime.        │
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
        $this->enabled = (bool) config('core_foundation.cache.global', true);
    }

    // =========================================================================
    // Read — cache-first strategy
    // =========================================================================

    /**
     * Cache the result of a repository read query.
     *
     * When enabled, results are stored forever under relation-aware tags.
     * When disabled, the callback is executed directly.
     *
     * @param  string  $method  Repository method name (for key building)
     * @param  array  $extra  Additional key discriminators
     * @param  bool  $shouldCache  Per-method override (from $cacheAllowedMethods)
     * @param  Closure  $callback  The actual database query
     */
    public function remember(
        BaseModel $model,
        string $method,
        array $filters,
        array $relations,
        array $columns,
        array $extra,
        bool $shouldCache,
        Closure $callback,
    ): mixed {
        if (! $this->enabled || ! $shouldCache) {
            return $callback();
        }

        $key = $this->keyBuilder->build($model, $method, $filters, $relations, $columns, $extra);
        $tags = $this->tagResolver->resolve($model, $relations);

        return $this->cacheForever($tags, $key, $callback);
    }

    // =========================================================================
    // Write invalidation
    // =========================================================================

    /**
     * Invalidate ALL cache entries for this model.
     * Call on create and delete.
     */
    public function flushModel(BaseModel $model): void
    {
        $tags = [$model->getTable()];

        $this->bustCache($tags);

        Log::info('Cache invalidated', [
            'model' => $model::class,
            'table' => $model->getTable(),
            'scope' => 'full',
        ]);
    }

    /**
     * Invalidate only cache entries related to a specific record.
     * Call on update — avoids busting queries for other records.
     */
    public function flushRecord(BaseModel $model, int|string $id): void
    {
        $recordKey = $this->keyBuilder->buildForRecord($model, $id);
        $tags = [$model->getTable()];

        // Forget the specific record key
        Cache::tags($tags)->forget($recordKey);

        // Also flush fetchById cache for this ID — always cached per record
        $fetchByIdKey = $this->keyBuilder->build($model, 'fetchById', extra: ['id' => $id]);
        Cache::tags($tags)->forget($fetchByIdKey);

        Log::info('Cache invalidated', [
            'model' => $model::class,
            'table' => $model->getTable(),
            'id' => $id,
            'scope' => 'record',
        ]);
    }

    // =========================================================================
    // Control
    // =========================================================================

    /**
     * Disable caching for this request/instance.
     * Useful in tests or when cache bypassing is needed.
     */
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
}
