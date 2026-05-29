<?php

namespace CoreFoundation\Repositories\Cache;

/**
 * TenantCacheScope
 *
 * The built-in scope for multi-tenant isolation.
 *
 * Wraps a tenant ID and produces the prefix "tenant:{id}" for all cache
 * tags and keys, guaranteeing complete isolation between tenants:
 *
 *   tenant:1:products:listing   (Tenant 1's product list queries)
 *   tenant:2:products:listing   (Tenant 2's product list queries)
 *   tenant:1:products:record:42 (A specific product for Tenant 1)
 *
 * Flushing Tenant 1's product cache never touches Tenant 2.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE — in a concrete repository:                                           │
 * │                                                                             │
 * │   protected function cacheScope(): CacheScope                               │
 * │   {                                                                         │
 * │       // resolve tenant from request context, ApplicationState, etc.        │
 * │       return new TenantCacheScope($this->tenantId());                       │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
final readonly class TenantCacheScope implements CacheScope
{
    public function __construct(private int|string $tenantId) {}

    public function prefix(): string
    {
        return "tenant:{$this->tenantId}";
    }
}
