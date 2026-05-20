<?php

namespace CoreFoundation\Repositories\Filter\Operators;

use Illuminate\Database\Eloquent\Builder;
use CoreFoundation\Repositories\Filter\Contracts\FilterOperator;

/**
 * AndOperator
 *
 * Handles nested AND groups.
 * Value must be an array of filter conditions.
 *
 * Request:
 *   filters[__and_][__gt_age]=18
 *   filters[__and_][__eq_active]=1
 *
 * Produces: WHERE (age > 18 AND active = 1)
 *
 * Note: Actual nested logic is handled by FilterApplicator::applyNestedCondition().
 */
final class AndOperator implements FilterOperator
{
    public function identifier(): string
    {
        return '__and_';
    }

    public function apply(Builder $builder, string $column, mixed $value): void
    {
        // Intentionally empty — FilterApplicator handles AND nesting directly.
    }
}
