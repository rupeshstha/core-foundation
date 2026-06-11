<?php

namespace CoreFoundation\Repositories\Cache;

/**
 * Accumulates cache tags busted during the current request lifecycle.
 *
 * Registered as scoped() — per-request in Octane, singleton in standard Laravel.
 * Read by AttachCacheHeaders middleware to emit X-Cache-Tags-Busted response header,
 * which the frontend SDK uses to invalidate matching client-side cache entries.
 */
final class CacheBustCollector
{
    private array $bustedTags = [];

    /** @param array<string> $tags */
    public function record(array $tags): void
    {
        $this->bustedTags = array_values(array_unique([...$this->bustedTags, ...$tags]));
    }

    /** @return array<string> */
    public function all(): array
    {
        return $this->bustedTags;
    }

    public function hasAny(): bool
    {
        return $this->bustedTags !== [];
    }

    public function clear(): void
    {
        $this->bustedTags = [];
    }
}
