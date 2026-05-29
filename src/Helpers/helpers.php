<?php

use CoreFoundation\DevTools\ServerTiming\ServerTimingService;

if (! function_exists('measureTiming')) {
    /**
     * Helper to start/stop or wrap a measurement in Server-Timing.
     *
     * USAGE:
     *   measureTiming('my-op'); // Start
     *   // ...
     *   measureTiming('my-op'); // Stop
     *
     *   measureTiming('my-op', fn() => ...); // Wrap
     */
    function measureTiming(string $key, ?callable $callable = null): mixed
    {
        /** @var ServerTimingService $timing */
        $timing = app(ServerTimingService::class);

        if (is_callable($callable)) {
            return $timing->wrap($key, $callable);
        }

        $timing->measure($key);

        return null;
    }
}
