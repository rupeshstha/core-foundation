<?php

namespace CoreFoundation\Facades;

use CoreFoundation\Facades\Services\ServerTimingFacadeService;
use Illuminate\Support\Facades\Facade;

class ServerTiming extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return ServerTimingFacadeService::class;
    }
}
