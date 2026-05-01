<?php

namespace CoreFoundation\Strategies;

use CoreFoundation\Contracts\StrategyContract;
use Illuminate\Support\Facades\Facade;

class Strategy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StrategyContract::class;
    }
}
