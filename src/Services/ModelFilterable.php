<?php

namespace CoreFoundation\Services;

use Closure;
use CoreFoundation\Entities\BaseModel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use CoreFoundation\Manipulators\ObjectMutable;
use Illuminate\Contracts\Pagination\Paginator;
use CoreFoundation\Http\Requests\ValidateFilterableRequest;

class ModelFilterable
{
    private array $queryOperators = [
        "__gt_" => ">",
        "__gte_" => ">=",
        "__lt_" => "<",
        "__lte_" => "<=",
        "__eq_" => "=",
        "__neq_" => "!=",
        "__in_" => "IN",
        "__nin_" => "NOT IN",
        "__like_" => "LIKE",
        "__nlike_" => "NOT LIKE",
        "__null_" => "IS NULL",
        "__nnull_" => "IS NOT NULL",
        "__or_" => "OR",
        "__and_" => "AND",
    ];

    private array $queryExpressions = [
        "__or_*" => "OR",
        "__and_*" => "AND",
    ];

    protected BaseModel $model;

    public function __construct(
        protected ValidateFilterableRequest $validateFilterableRequest,
    ) {
    }

    public function setModel(BaseModel $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getFiltered(Builder $builder, array $requestFilters = []): Collection|Paginator
    {
        $requestFilters = $this->validateFilterableRequest->validated();

        $filters = $requestFilters["filters"] ?? [];
        $this->buildFilterableQuery($builder, $filters);
        $this->buildSortableQuery($builder, $filters);

        $filtered = $this->pagination($builder, $requestFilters);

        return $filtered;
    }

    private function buildFilterableQuery(Builder $builder, array $filters): void
    {
        foreach ($filters as $columKeyWithFilterKey => $filterValue) {
            // Filter keys should be in proper format. ie: __in_{column_name}.
            // It will skip if passed filter is not in proper format/
            preg_match("/^__[a-zA-Z]+_/", $columKeyWithFilterKey, $validKey);

            if (! count($validKey)) {
                continue;
            }
            $filterIdentifier = end($validKey);

            // It will only filters through defined queryOperators keys.
            if (! isset($this->queryOperators[$filterIdentifier])) {
                continue;
            }
            $column = str_replace($filterIdentifier, "", $columKeyWithFilterKey);

            // TODO: refactor to simpler version.
            // Handles nested where conditions.
            if (
                $filterIdentifier == "__or_"
            ) {
                $builder->orWhere(function (Builder $query) use ($filterValue) {
                    $this->buildFilterableQuery($query, $filterValue);
                });
            } elseif (
                $filterIdentifier == "__and_"
            ) {
                $builder->where(function (Builder $query) use ($filterValue) {
                    $this->buildFilterableQuery($query, $filterValue);
                });
            }

            // It will only filter column based on defined searchable columns on model.
            if (! in_array($column, $this->model::searchable())) {
                continue;
            }

            $this->applyFilterQuery($filterIdentifier)
                ->call(
                    $this, $builder, $column, $filterValue
                );
        }
    }

    private function applyFilterQuery(string $operator): Closure
    {
        $filter = match ($operator) {
            "__eq_", "__gt_", "__gte_",
            "__lt_", "__lte_", "__neq_",
            "__like_", "__nlike_", "__null_",
            "__nnull_" => $this->defaultQueryFilter($operator),
            "__in_" => $this->inFilter(),
            "__nin_" => $this->notInFilter(),
            default => $this->defaultFilter()
        };

        return $filter;
    }

    private function defaultQueryFilter(string $currentOperator): Closure
    {
        $queryOperator = $this->queryOperators[$currentOperator];
        return function (Builder $builder, string $column, mixed $value) use ($queryOperator) {
            $builder->where($column, $queryOperator, $value);
        };
    }

    private function inFilter(): Closure
    {
        return function (Builder $builder, string $column, array $value) {
            $builder->whereIn($column, $value);
        };
    }

    private function notInFilter(): Closure
    {
        return function (Builder $builder, string $column, array $value) {
            $builder->whereNotIn($column, $value);
        };
    }

    private function defaultFilter(): Closure
    {
        return function (Builder $builder, string $column, mixed $value) {
            $builder->whereLike($column, $value);
        };
    }

    private function pagination(Builder $builder, array $requestFilters = []): Collection|Paginator
    {
        $perPage = (int) ($requestFilters["per_page"] ?? 25);
        $paginate = $requestFilters["no_paginate"] ?? false;
        $resources = !$paginate
            ? $builder->paginate($perPage)->appends(request()->except("page"))
            : $builder->get();
        return $resources;
    }

    private function buildSortableQuery(Builder $builder, array $filters): void
    {
        // add sortable
    }
}
