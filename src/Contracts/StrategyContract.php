<?php

namespace CoreFoundation\Contracts;

use Illuminate\Support\Fluent;

interface StrategyContract
{
    public function get(string $type, ?string $identifier): Fluent;

    public function instance(string $type, ?string $identifier): mixed;
}
