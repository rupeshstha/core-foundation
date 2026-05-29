<?php

namespace CoreFoundation\Repositories\Filter\Operators;

use Illuminate\Database\Eloquent\Builder;
use CoreFoundation\Repositories\Filter\Contracts\FilterOperator;

class IsNotNullOperator implements FilterOperator
{
    public function identifier(): string
    {
        return '__nnull_';
    }

    public function apply(Builder $builder, string $column, mixed $value): void
    {
        $builder->whereNotNull($column);
    }
}
