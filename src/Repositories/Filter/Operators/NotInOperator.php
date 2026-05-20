<?php

namespace CoreFoundation\Repositories\Filter\Operators;

use Illuminate\Database\Eloquent\Builder;
use CoreFoundation\Repositories\Filter\Contracts\FilterOperator;

final class NotInOperator implements FilterOperator
{
    public function identifier(): string
    {
        return '__nin_';
    }

    public function apply(Builder $builder, string $column, mixed $value): void
    {
        $builder->whereNotIn($column, (array) $value);
    }
}
