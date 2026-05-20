<?php

namespace CoreFoundation\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * WriteRepositoryContract
 *
 * Contract for write operations.
 * Concrete repositories that are intentionally read-only skip this.
 */
interface WriteRepositoryContract
{
    public function create(array $attributes): Model;

    public function update(int|string $id, array $attributes): Model;

    public function delete(int|string $id): bool;
}
