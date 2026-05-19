<?php

namespace CoreFoundation\Traits;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * HasCacheable
 *
 * Tag-based cache helpers for service classes.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ DRIVER REQUIREMENT                                                          │
 * │                                                                             │
 * │ Tag-based caching requires Redis or Memcached.                              │
 * │ File and database cache drivers do NOT support tags.                        │
 * │ Set CACHE_DRIVER=redis in your .env for this to work.                       │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ KEY NAMING CONVENTION                                                       │
 * │                                                                             │
 * │ Always namespace cache keys by domain to prevent collisions:                │
 * │   "{domain}.{entity}.{id}"  e.g. "orders.detail.42"                         │
 * │   "{domain}.{list}"         e.g. "orders.list"                              │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE PATTERN                                                               │
 * │                                                                             │
 * │   // Read-through cache (forever, busted on write):                         │
 * │   public function find(int $id): ObjectMutable                              │
 * │   {                                                                         │
 * │       return $this->cacheForever(                                           │
 * │           tags:    ['orders'],                                              │
 * │           key:     "orders.detail.{$id}",                                   │
 * │           closure: fn () => ObjectMutable::from(                            │
 * │               Order::findOrFail($id)->toArray()                             │
 * │           ),                                                                │
 * │       );                                                                    │
 * │   }                                                                         │
 * │                                                                             │
 * │   // Cache with TTL:                                                        │
 * │   public function summary(): array                                          │
 * │   {                                                                         │
 * │       return $this->cacheTtl(                                               │
 * │           tags:    ['orders', 'orders.summary'],                            │
 * │           key:     'orders.summary',                                        │
 * │           ttl:     3600,                                                    │
 * │           closure: fn () => $this->computeSummary(),                        │
 * │       );                                                                    │
 * │   }                                                                         │
 * │                                                                             │
 * │   // Always flush on write:                                                 │
 * │   public function place(ObjectMutable $data): ObjectMutable                 │
 * │   {                                                                         │
 * │       $result = ...;                                                        │
 * │       $this->bustCache(['orders']);                                          │
 * │       return $result;                                                       │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait HasCacheable
{
    /**
     * Cache a value forever under the given tags and key.
     * Only invalidated when bustCache() is called with a matching tag.
     *
     * @param  array<string>  $tags
     * @param  string  $key  Namespaced cache key e.g. 'orders.detail.42'
     * @param  Closure(): mixed  $closure  Value factory, called only on cache miss
     */
    final protected function cacheForever(array $tags, string $key, Closure $closure): mixed
    {
        return Cache::tags($tags)->rememberForever($key, $closure);
    }

    /**
     * Cache a value for the given number of seconds under the given tags and key.
     * Expires after TTL or earlier if bustCache() is called with a matching tag.
     *
     * @param  array<string>  $tags
     * @param  string  $key  Namespaced cache key e.g. 'orders.list'
     * @param  int  $ttl  Time-to-live in seconds
     * @param  Closure(): mixed  $closure  Value factory, called only on cache miss
     */
    final protected function cacheTtl(array $tags, string $key, int $ttl, Closure $closure): mixed
    {
        return Cache::tags($tags)->remember($key, $ttl, $closure);
    }

    /**
     * Invalidate all cache entries associated with the given tags.
     * Call this in any service method that mutates data covered by those tags.
     *
     * @param  array<string>  $tags
     */
    final protected function bustCache(array $tags): void
    {
        Cache::tags($tags)->flush();
    }

    /**
     * Remove a single cache entry by key without flushing the entire tag group.
     * Use when you want surgical invalidation of one entry, not the whole domain.
     *
     * @param  array<string>  $tags
     */
    final protected function forgetCache(array $tags, string $key): void
    {
        Cache::tags($tags)->forget($key);
    }
}
