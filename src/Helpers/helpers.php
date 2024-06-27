<?php

use CoreFoundation\Facades\Services\ServerTimingFacadeService;

if (! function_exists('measureTiming')) {
    function measureTiming(string $key, callable $callable = null): void
    {
        /** @var ServerTimingFacadeService $timing */
        $timing = app(ServerTimingFacadeService::class);

        if (is_callable($callable)) {
            $timing->setDuration($key, $callable);
            return;
        }

        $timing->measure($key);
    }
}
