<?php

namespace CoreFoundation\Repositories\Sort;

use Illuminate\Database\Eloquent\Builder;

/**
 * SortApplicator
 *
 * Applies sort parameters to an Eloquent Builder.
 * Separate class from FilterApplicator — unified request, separate concern.
 * Pure — takes an array of sort params, returns a mutated Builder.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ SORT FORMAT                                                                 │
 * │                                                                             │
 * │ Request: sort[]=name&sort[]=-created_at                                     │
 * │                                                                             │
 * │ Prefix '-' means descending. No prefix means ascending.                    │
 * │   sort[]=name          → ORDER BY name ASC                                 │
 * │   sort[]=-created_at   → ORDER BY created_at DESC                          │
 * │   sort[]=name&sort[]=-created_at → ORDER BY name ASC, created_at DESC     │
 * │                                                                             │
 * │ Alternatively as key=>direction pairs:                                      │
 * │   sort[name]=asc&sort[created_at]=desc                                      │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
final class SortApplicator
{
    /**
     * Apply sort parameters to the given Builder.
     *
     * @param  Builder  $builder  The query to sort
     * @param  array  $sorts  Sort params from request
     * @param  array<string>  $allowedColumns  Sortable column whitelist
     */
    public function apply(Builder $builder, array $sorts, array $allowedColumns): Builder
    {
        foreach ($sorts as $key => $value) {
            [$column, $direction] = $this->resolveSort($key, $value);

            if (! in_array($column, $allowedColumns, true)) {
                continue; // Security — reject columns not in whitelist
            }

            $builder->orderBy($column, $direction);
        }

        return $builder;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Resolve a sort entry to [column, direction].
     *
     * Handles both formats:
     *   - Index array: sort[]=-created_at (prefix '-' = desc)
     *   - Assoc array: sort[name]=asc
     */
    private function resolveSort(int|string $key, string $value): array
    {
        // Assoc format: sort[created_at]=desc
        if (is_string($key) && ! empty($key)) {
            return [$key, $this->normaliseDirection($value)];
        }

        // Index format: sort[]=-created_at
        if (str_starts_with($value, '-')) {
            return [ltrim($value, '-'), 'desc'];
        }

        return [$value, 'asc'];
    }

    private function normaliseDirection(string $direction): string
    {
        return strtolower($direction) === 'desc' ? 'desc' : 'asc';
    }
}
