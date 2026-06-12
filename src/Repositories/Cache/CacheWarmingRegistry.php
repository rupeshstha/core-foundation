<?php

namespace CoreFoundation\Repositories\Cache;

use CoreFoundation\Repositories\Cache\Contracts\CacheWarmer;

/**
 * CacheWarmingRegistry
 *
 * Registry for all cache warmers in the system.
 */
final class CacheWarmingRegistry
{
    /** @var array<string, CacheWarmer> */
    private array $warmers = [];

    public function register(CacheWarmer $warmer): void
    {
        $this->warmers[$warmer->name()] = $warmer;
    }

    /** @return array<string, CacheWarmer> */
    public function all(): array
    {
        return $this->warmers;
    }

    public function get(string $name): ?CacheWarmer
    {
        return $this->warmers[$name] ?? null;
    }
}
