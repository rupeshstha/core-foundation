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

    /**
     * $quiet: true updates via the model's own updateQuietly() — same
     * mechanism Eloquent itself uses (Model::withoutEvents()) — so no
     * model events/observers fire, and cache is correspondingly left
     * unflushed. See BaseRepository::update() for when this is safe.
     */
    public function update(int|string $id, array $attributes, bool $quiet = false): Model;

    /**
     * Perform an atomic update (Compare-and-Swap).
     * The update only proceeds if the database record matches the provided conditions.
     * Throws StaleDataException if 0 rows are affected (indicating a state mismatch).
     */
    public function updateAtomic(int|string $id, array $attributes, array $conditions): Model;

    public function delete(int|string $id): bool;
}
