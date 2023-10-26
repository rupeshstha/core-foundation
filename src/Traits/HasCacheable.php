<?php

namespace CoreFoundation\Traits;

use Closure;
use Illuminate\Support\Facades\Cache;

trait HasCacheable
{
    public function storeTTlCache()
    {

    }

    public function storeTagCache(array $tags, string $key, Closure $closure): mixed
    {
        return Cache::tags($tags)->rememberForever($key, $closure);
    }

    public function storeTTlCacheByTags()
    {

    }

    public function flushTagCache(array $tags): void
    {
        Cache::tags($tags)->flush();
    }
}
