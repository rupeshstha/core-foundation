<?php

namespace CoreFoundation\Repositories\Cache;

use InvalidArgumentException;

/**
 * PrefixCacheScope
 *
 * A simple scope implementation that allows any string prefix to be used
 * for cache isolation (e.g., "tenant:1", "org:abc").
 */
final readonly class PrefixCacheScope implements CacheScope
{
    /**
     * @param  non-empty-string  $prefix
     */
    public function __construct(private string $prefix)
    {
        throw_if(
            condition: $prefix === '',
            exception: new InvalidArgumentException('PrefixCacheScope prefix must not be empty — an empty prefix provides no cache isolation.'),
        );
    }

    /**
     * @return non-empty-string
     */
    public function prefix(): string
    {
        return $this->prefix;
    }
}
