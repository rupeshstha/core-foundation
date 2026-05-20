<?php

namespace CoreFoundation\Repositories\Filter\Operators;

use Illuminate\Database\Eloquent\Builder;
use CoreFoundation\Repositories\Filter\Contracts\FilterOperator;

final class LessThanOrEqualOperator implements FilterOperator
{
    public function identifier(): string
    {
        return '__lte_';
    }

    public function apply(Builder $builder, string $column, mixed $value): void
    {
        $builder->where($column, '<=', $value);
    }
}
