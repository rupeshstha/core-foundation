<?php

namespace CoreFoundation\Repositories\Cache;

/**
 * CacheScope
 *
 * Defines an isolation boundary for cache operations.
 *
 * The most common scope is tenant isolation — all cache tags and keys for a
 * given tenant are prefixed with a unique identifier. This ensures that
 * data for Tenant A is never returned to a request from Tenant B.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE                                                                       │
 * │                                                                             │
 * │   public function cacheScope(): ?CacheScope                                 │
 * │   {                                                                         │
 * │       return new PrefixCacheScope("tenant:{$this->tenantId}");              │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
interface CacheScope
{
    /**
     * The unique prefix for this scope.
     *
     * Must be unique per isolation unit. For tenants: "tenant:{id}".
     * Must be URL-safe (no spaces, no cache-reserved characters).
     *
     * @return non-empty-string
     */
    public function prefix(): string;
}
