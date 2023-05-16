<?php

namespace Rupeshstha\CoreFoundation\Strategies;

use Illuminate\Support\Facades\Facade;
use Rupeshstha\CoreFoundation\Contracts\StrategyContract;

class Strategy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StrategyContract::class;
    }
}
