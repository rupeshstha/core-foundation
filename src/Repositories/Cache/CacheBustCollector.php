<?php

namespace CoreFoundation\Repositories\Cache;

/**
 * Accumulates cache tags busted during the current request lifecycle.
 *
 * Registered as scoped() — per-request in Octane, singleton in standard Laravel.
 * Read by AttachCacheHeaders middleware to emit X-Cache-Tags-Busted response header.
 */
final class CacheBustCollector
{
    private array $bustedTags = [];

    /** @var int Cap to prevent HTTP header bloat (~8KB limits in most proxies) */
    private int $cap = 50;

    private bool $isCapped = false;

    /** @param array<string> $tags */
    public function record(array $tags): void
    {
        foreach ($tags as $tag) {
            if (count($this->bustedTags) >= $this->cap) {
                $this->isCapped = true;
                break;
            }

            if (! in_array($tag, $this->bustedTags, true)) {
                $this->bustedTags[] = $tag;
            }
        }
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

    public function isCapped(): bool
    {
        return $this->isCapped;
    }

    public function clear(): void
    {
        $this->bustedTags = [];
        $this->isCapped = false;
    }
}
