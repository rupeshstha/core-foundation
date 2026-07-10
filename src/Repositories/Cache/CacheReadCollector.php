<?php

namespace CoreFoundation\Repositories\Cache;

/**
 * Accumulates cache tags associated with responses read during the current request.
 *
 * Registered as scoped() — per-request in Octane, singleton in standard Laravel.
 * Read by AttachReadTags middleware to emit the Surrogate-Key response header,
 * which tells CDN edge caches (Fastly, CloudFront, Nginx) which tags to associate
 * with the cached response so they can be surgically purged on writes.
 */
final class CacheReadCollector
{
    private array $readTags = [];

    /** @var int Cap to prevent HTTP header bloat (~8KB limits in most proxies) */
    private int $cap = 50;

    private bool $isCapped = false;

    /** @param array<string> $tags */
    public function record(array $tags): void
    {
        foreach ($tags as $tag) {
            if (count($this->readTags) >= $this->cap) {
                $this->isCapped = true;
                break;
            }

            if (! in_array($tag, $this->readTags, true)) {
                $this->readTags[] = $tag;
            }
        }
    }

    /** @return array<string> */
    public function all(): array
    {
        return $this->readTags;
    }

    public function hasAny(): bool
    {
        return $this->readTags !== [];
    }

    public function isCapped(): bool
    {
        return $this->isCapped;
    }

    public function clear(): void
    {
        $this->readTags = [];
        $this->isCapped = false;
    }
}
