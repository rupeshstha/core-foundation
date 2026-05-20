<?php

namespace CoreFoundation\Repositories\Contracts;

use CoreFoundation\Entities\BaseModel;

/**
 * RepositoryContract
 *
 * Full CRUD + Query contract. BaseRepository implements this.
 * Concrete repos may implement only Read or Write when appropriate.
 */
interface RepositoryContract extends QueryRepositoryContract, ReadRepositoryContract, WriteRepositoryContract
{
    public function getModel(): BaseModel;
}
