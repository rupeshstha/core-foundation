<?php

namespace CoreFoundation\Repositories\Filter\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * FilterOperator
 *
 * Contract for every filter operator.
 * Each operator is a single-responsibility class that applies
 * one type of WHERE clause to the query builder.
 *
 * To add a custom operator:
 *
 *   class BetweenOperator implements FilterOperator
 *   {
 *       public function identifier(): string { return '__between_'; }
 *
 *       public function apply(Builder $builder, string $column, mixed $value): void
 *       {
 *           [$min, $max] = $value;
 *           $builder->whereBetween($column, [$min, $max]);
 *       }
 *   }
 *
 * Register in ServiceProvider::boot():
 *   FilterApplicator::addOperator(new BetweenOperator);
 */
interface FilterOperator
{
    /**
     * The prefix identifier this operator handles.
     * e.g. '__eq_', '__like_', '__gt_'
     */
    public function identifier(): string;

    /**
     * Apply the operator's WHERE clause to the builder.
     *
     * @param  string  $column  The resolved column name (identifier stripped)
     * @param  mixed  $value  The filter value from the request
     */
    public function apply(Builder $builder, string $column, mixed $value): void;
}
