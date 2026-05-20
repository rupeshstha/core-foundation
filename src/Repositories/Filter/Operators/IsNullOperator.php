<?php

namespace CoreFoundation\Repositories\Filter\Operators;

use Illuminate\Database\Eloquent\Builder;
use CoreFoundation\Repositories\Filter\Contracts\FilterOperator;

final class IsNullOperator implements FilterOperator
{
    public function identifier(): string
    {
        return '__null_';
    }

    public function apply(Builder $builder, string $column, mixed $value): void
    {
        $builder->whereNull($column);
    }
}
