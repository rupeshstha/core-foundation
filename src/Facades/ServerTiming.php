<?php

namespace CoreFoundation\Facades;

use Illuminate\Support\Facades\Facade;
use CoreFoundation\Facades\Services\ServerTimingFacadeService;

class ServerTiming extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ServerTimingFacadeService::class;
    }
}
