<?php

namespace CoreFoundation\Repositories\Filter\Operators;

use Illuminate\Database\Eloquent\Builder;
use CoreFoundation\Repositories\Filter\Contracts\FilterOperator;

/**
 * OrOperator
 *
 * Handles nested OR groups.
 * Value must be an array of filter conditions.
 *
 * Request:
 *   filters[__or_][__eq_status]=active
 *   filters[__or_][__eq_role]=admin
 *
 * Produces: WHERE (status = 'active' OR role = 'admin')
 *
 * Note: Actual nested logic is handled by FilterApplicator::applyNestedCondition().
 * This class exists so the operator registry resolves the identifier correctly
 * and so it can be swapped/removed via config like any other operator.
 */
final class OrOperator implements FilterOperator
{
    public function identifier(): string
    {
        return '__or_';
    }

    public function apply(Builder $builder, string $column, mixed $value): void
    {
        // Intentionally empty — FilterApplicator handles OR nesting directly
        // via applyNestedCondition() before reaching this apply() call.
    }
}
