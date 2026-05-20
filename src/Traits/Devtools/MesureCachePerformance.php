<?php

namespace CoreFoundation\Traits\Devtools;

use Closure;
use CoreFoundation\Traits\HasCacheable;
use CoreFoundation\Facades\ServerTiming;

/**
 * MeasuresCachePerformance
 *
 * Extends HasCacheable with Server-Timing instrumentation.
 * Overrides cacheForever() and cacheTtl() to record hit/miss timing.
 * Opt-in per service — add this trait alongside HasCacheable.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE                                                                       │
 * │                                                                             │
 * │   class OrderService extends BaseService                                    │
 * │   {                                                                         │
 * │       use MeasuresCachePerformance;  // replaces HasCacheable               │
 * │                                                                             │
 * │       public function find(int $id): PlaceOrderData                         │
 * │       {                                                                     │
 * │           return $this->cacheForever(                                       │
 * │               tags:    ['orders'],                                           │
 * │               key:     "orders.detail.{$id}",                               │
 * │               closure: fn () => PlaceOrderData::fromArray(...),             │
 * │           );                                                                │
 * │           // Automatically recorded as "cache:orders.detail.42;hit" or     │
 * │           // "cache:orders.detail.42;miss" in Server-Timing                 │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait MeasuresCachePerformance
{
    use HasCacheable {
        cacheForever as private parentCacheForever;
        cacheTtl as private parentCacheTtl;
    }

    /**
     * Cache forever with Server-Timing hit/miss recording.
     */
    final protected function cacheForever(array $tags, string $key, Closure $closure): mixed
    {
        return $this->measuredCache($key, fn () => $this->parentCacheForever($tags, $key, $closure));
    }

    /**
     * Cache with TTL with Server-Timing hit/miss recording.
     */
    final protected function cacheTtl(array $tags, string $key, int $ttl, Closure $closure): mixed
    {
        return $this->measuredCache($key, fn () => $this->parentCacheTtl($tags, $key, $ttl, $closure));
    }

    /**
     * Wrap a cache call and record whether it was a hit or miss.
     *
     * The metric name encodes both the key and hit/miss status so DevTools
     * shows them as separate entries: "cache:orders.detail.42" with desc "hit" or "miss".
     */
    private function measuredCache(string $key, Closure $cacheCall): mixed
    {
        $start = microtime(true);
        $result = $cacheCall();
        $elapsed = (microtime(true) - $start) * 1000;

        // A very fast result (< 1ms) almost always came from the cache.
        // This heuristic is imperfect but avoids requiring a cache driver
        // that supports explicit hit/miss reporting.
        $hitOrMiss = $elapsed < 1.0 ? 'hit' : 'miss';

        ServerTiming::record(
            name: "cache:{$key}",
            durationMs: round($elapsed, 2),
            description: "Cache {$hitOrMiss}: {$key}",
        );

        return $result;
    }
}
