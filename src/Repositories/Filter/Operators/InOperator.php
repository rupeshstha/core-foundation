<?php

namespace CoreFoundation\Repositories\Filter\Operators;

use Illuminate\Database\Eloquent\Builder;
use CoreFoundation\Repositories\Filter\Contracts\FilterOperator;

final class InOperator implements FilterOperator
{
    public function identifier(): string
    {
        return '__in_';
    }

    public function apply(Builder $builder, string $column, mixed $value): void
    {
        $builder->whereIn($column, (array) $value);
    }
}
