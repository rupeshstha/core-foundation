<?php

namespace CoreFoundation\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * ReadRepositoryContract
 *
 * Contract for read operations. Implement on read-only or read-heavy repositories.
 * Separating from WriteRepositoryContract enables CQRS-lite patterns.
 */
interface ReadRepositoryContract
{
    /**
     * Fetch all records, optionally filtered, sorted, and paginated.
     *
     * @param  array  $filters  Validated filter + sort parameters
     * @param  array  $relations  Eager-load relation names
     * @param  array  $columns  Columns to select
     * @param  bool  $paginate  Whether to paginate — defaults to true
     * @param  int  $perPage  Records per page when paginating
     */
    public function fetchAll(
        array $filters = [],
        array $relations = [],
        array $columns = ['*'],
        bool $paginate = true,
        int $perPage = 25,
    ): Collection|LengthAwarePaginator;

    /**
     * Find a single record by primary key.
     *
     * @param  array  $relations  Eager-load relation names
     * @param  array  $columns  Columns to select
     */
    public function fetchById(
        int|string $id,
        array $relations = [],
        array $columns = ['*'],
    ): ?Model;

    /**
     * Apply a "FOR UPDATE" pessimistic lock to the next read query.
     */
    public function lockForUpdate(): static;

    /**
     * Apply a "FOR SHARE" pessimistic shared lock to the next read query.
     */
    public function sharedLock(): static;
}
