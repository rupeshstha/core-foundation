<?php

namespace CoreFoundation\Repositories\Contracts;

use Illuminate\Database\Eloquent\Builder;

/**
 * QueryRepositoryContract
 *
 * Entry point for custom queries that do not fit standard CRUD.
 * Custom queries live in concrete repositories starting from query().
 * This keeps BaseRepository lean.
 *
 * USAGE:
 *
 *   class OrderRepository extends BaseRepository implements QueryRepositoryContract
 *   {
 *       public function pendingOlderThan(int $days): Collection
 *       {
 *           return $this->query()
 *               ->where("status", "pending")
 *               ->where("created_at", "<", now()->subDays($days))
 *               ->get();
 *       }
 *   }
 */
interface QueryRepositoryContract
{
    /**
     * Return a fresh Eloquent Builder for this repository\'s model.
     * Starting point for all custom queries in concrete repositories.
     */
    public function query(): Builder;
}
