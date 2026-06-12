<?php

namespace CoreFoundation\Repositories\Cache\Contracts;

/**
 * CacheWarmer
 *
 * Interface for classes that can proactively populate the cache with
 * expensive computations or frequently accessed data.
 */
interface CacheWarmer
{
    /**
     * Execute the warming logic.
     *
     * Should be designed to be run as a background job or a scheduled task.
     *
     * @param array $context Optional context (e.g. ['tenant_id' => 1])
     */
    public function warm(array $context = []): void;

    /**
     * Unique identifier for this warmer.
     */
    public function name(): string;
}
