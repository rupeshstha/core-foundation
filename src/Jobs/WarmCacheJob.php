<?php

namespace CoreFoundation\Jobs;

use CoreFoundation\Repositories\Cache\CacheWarmingRegistry;
use Throwable;

/**
 * WarmCacheJob
 *
 * Background job to execute a specific cache warmer for a specific tenant.
 * Enables parallel cache warming across a worker cluster.
 */
class WarmCacheJob extends BaseJob
{
    public function __construct(
        protected string $warmerName,
        protected array $context = []
    ) {}

    public function handle(CacheWarmingRegistry $registry): void
    {
        $warmer = $registry->get($this->warmerName);

        if (! $warmer) {
            logger()->warning("[CacheWarming] Warmer [{$this->warmerName}] not found in registry.");
            return;
        }

        $warmer->warm($this->context);
    }

    protected function logContext(Throwable $exception): array
    {
        return [
            'warmer' => $this->warmerName,
            'context' => $this->context,
        ];
    }
}
