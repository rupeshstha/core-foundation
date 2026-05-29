<?php

namespace CoreFoundation\Repositories\Cache;

/**
 * CacheScope
 *
 * Defines an isolation boundary for cache operations.
 *
 * The most common scope is tenant isolation — all cache tags and keys for a
 * given tenant are prefixed so that flushing one tenant's cache never touches
 * another tenant's data.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE — in a concrete repository:                                           │
 * │                                                                             │
 * │   protected function cacheScope(): ?CacheScope                              │
 * │   {                                                                         │
 * │       return new TenantCacheScope(tenant()->id);                            │
 * │   }                                                                         │
 * │                                                                             │
 * │ Or using the provided NullCacheScope to explicitly opt out:                 │
 * │                                                                             │
 * │   protected function cacheScope(): ?CacheScope                              │
 * │   {                                                                         │
 * │       return null;   // global (unscoped) cache — backward compatible       │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
interface CacheScope
{
    /**
     * The prefix prepended to all cache tags and keys for this scope.
     *
     * Must be unique per isolation unit. For tenants: "tenant:{id}".
     * Must be URL-safe (no spaces, no cache-reserved characters).
     *
     * @return non-empty-string
     */
    public function prefix(): string;
}
