<?php

namespace CoreFoundation\Repositories\Contracts;

use Illuminate\Database\Eloquent\Model;

/**
 * RepositoryContract
 *
 * Full CRUD + Read contract. BaseRepository implements this.
 * Concrete repos may implement only Read or Write when appropriate.
 *
 * Deliberately does NOT expose query() — the fresh-Builder entry point for
 * custom queries is `protected` on BaseRepository. A repository's public
 * surface is its named methods (fetchAll(), fetchById(), plus whatever
 * domain-specific methods a concrete repository adds), never a raw Builder
 * handed to calling code. See BaseRepository::query() for why.
 */
interface RepositoryContract extends ReadRepositoryContract, WriteRepositoryContract
{
    public function getModel(): Model;
}
