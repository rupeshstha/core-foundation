<?php

namespace CoreFoundation\Strategies;

use Illuminate\Support\Facades\Facade;
use CoreFoundation\Contracts\StrategyContract;

class Strategy extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return StrategyContract::class;
    }
}
