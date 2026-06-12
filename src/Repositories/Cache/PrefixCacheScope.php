<?php

namespace CoreFoundation\Repositories\Cache;

/**
 * PrefixCacheScope
 *
 * A simple scope implementation that allows any string prefix to be used
 * for cache isolation (e.g., "tenant:1", "org:abc").
 */
final readonly class PrefixCacheScope implements CacheScope
{
    public function __construct(private string $prefix) {}

    public function prefix(): string
    {
        return $this->prefix;
    }
}
