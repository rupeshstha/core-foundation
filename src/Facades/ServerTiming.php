<?php

namespace CoreFoundation\Facades;

use CoreFoundation\DevTools\ServerTiming\ServerTimingService;
use Illuminate\Support\Facades\Facade;

/**
 * ServerTiming Facade
 *
 * @method static \CoreFoundation\DevTools\ServerTiming\ServerTimingService start(string $name, ?string $description = null)
 * @method static \CoreFoundation\DevTools\ServerTiming\ServerTimingService stop(string $name)
 * @method static \CoreFoundation\DevTools\ServerTiming\ServerTimingService measure(string $name, ?string $description = null)
 * @method static \CoreFoundation\DevTools\ServerTiming\ServerTimingService record(string $name, float $durationMs, ?string $description = null)
 * @method static mixed                                                      wrap(string $name, callable $callable, ?string $description = null)
 * @method static \CoreFoundation\DevTools\ServerTiming\ServerTimingService label(string $name, string|int|float $value)
 * @method static void                                                       flush()
 * @method static void                                                       reset()
 * @method static string                                                     toHeaderValue()
 * @method static bool                                                       isEmpty()
 * @method static array                                                      all()
 *
 * @see \CoreFoundation\DevTools\ServerTiming\ServerTimingService
 */
class ServerTiming extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ServerTimingService::class;
    }
}
