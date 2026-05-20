<?php

namespace CoreFoundation\Repositories\Filter\Operators;

use Illuminate\Database\Eloquent\Builder;
use CoreFoundation\Repositories\Filter\FilterApplicator;
use CoreFoundation\Repositories\Filter\Contracts\FilterOperator;

/**
 * AndOrOperator
 *
 * Handles groups of AND conditions combined with OR between groups.
 * Allows expressing: WHERE (a = 1 AND b = 2) OR (c = 3 AND d = 4)
 * in a single filter key.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ IDENTIFIER                                                                  │
 * │   __andor_                                                                  │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ REQUEST FORMAT                                                              │
 * │                                                                             │
 * │ Value must be an array of groups. Each group is an array of filter          │
 * │ conditions that are ANDed together. Groups are ORed between each other.     │
 * │                                                                             │
 * │ PHP array form:                                                             │
 * │                                                                             │
 * │   filters[__andor_][0][__eq_status]  = active                              │
 * │   filters[__andor_][0][__gt_age]     = 18                                  │
 * │   filters[__andor_][1][__eq_status]  = pending                             │
 * │   filters[__andor_][1][__eq_role]    = admin                               │
 * │                                                                             │
 * │ Produces:                                                                   │
 * │   WHERE                                                                     │
 * │     (status = 'active'  AND age > 18)                                      │
 * │     OR                                                                      │
 * │     (status = 'pending' AND role = 'admin')                                │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ HOW IT DIFFERS FROM NESTING __or_ + __and_                                  │
 * │                                                                             │
 * │ The nested __or_/__and_ approach requires two levels of nesting:            │
 * │   filters[__or_][0][__and_][__eq_status]=active                            │
 * │   filters[__or_][0][__and_][__gt_age]=18                                   │
 * │                                                                             │
 * │ __andor_ collapses this into one level — each indexed group is implicitly  │
 * │ ANDed, and the groups themselves are ORed. Cleaner for frontend clients     │
 * │ that need to express multi-condition OR filters.                            │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ REGISTRATION                                                                │
 * │                                                                             │
 * │ Add to config/repository.php operators:                                     │
 * │   '__andor_' => AndOrOperator::class,                                       │
 * │                                                                             │
 * │ Or from ServiceProvider::boot():                                            │
 * │   FilterApplicator::addOperator(new AndOrOperator);                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
final class AndOrOperator implements FilterOperator
{
    public function identifier(): string
    {
        return '__andor_';
    }

    /**
     * Apply grouped AND conditions combined with OR between groups.
     *
     * @param  string  $column  Not used — AndOrOperator operates on groups, not columns
     * @param  mixed  $value  Array of groups: [ [filters...], [filters...] ]
     */
    public function apply(Builder $builder, string $column, mixed $value): void
    {
        if (! is_array($value) || empty($value)) {
            return;
        }

        $builder->where(function (Builder $query) use ($value) {
            foreach ($value as $index => $group) {
                if (! is_array($group) || empty($group)) {
                    continue;
                }

                $method = $index === 0 ? 'where' : 'orWhere';

                $query->$method(function (Builder $groupQuery) use ($group) {
                    // Each condition within a group is ANDed
                    foreach ($group as $filterKey => $filterValue) {
                        $this->applyCondition($groupQuery, (string) $filterKey, $filterValue);
                    }
                });
            }
        });
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Apply a single filter condition within a group.
     * Resolves the operator from FilterApplicator's registry and delegates.
     *
     * Cannot call FilterApplicator::apply() directly here to avoid circular
     * dependency — instead resolves the operator class from the registry.
     */
    private function applyCondition(Builder $builder, string $key, mixed $value): void
    {
        $identifier = $this->extractIdentifier($key);

        if ($identifier === null) {
            return;
        }

        $operators = FilterApplicator::operators();

        $operator = $operators[$identifier] ?? null;

        if ($operator === null) {
            return;
        }

        // Skip nested grouping operators inside an andor group — not supported
        if (in_array($identifier, ['__or_', '__and_', '__andor_'], true)) {
            return;
        }

        $column = str_replace($identifier, '', $key);

        // Note: column whitelist validation happens at the FilterApplicator level
        // before this operator is called. Individual conditions here trust the
        // caller already validated the allowed columns at the top level.
        $operator->apply($builder, $column, $value);
    }

    private function extractIdentifier(string $key): ?string
    {
        if (! str_starts_with($key, '__')) {
            return null;
        }

        preg_match('/^(__[a-z]+_)/', $key, $matches);

        return $matches[1] ?? null;
    }
}
