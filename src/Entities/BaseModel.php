<?php

namespace CoreFoundation\Entities;

use CoreFoundation\Traits\ModelFillables;
use CoreFoundation\Traits\Searchable;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    use ModelFillables;
    use Searchable;

    /**
     * Get resolved relations that are binded from service container.
     */
    public static function getBindRelations(): array
    {
        return static::$relationResolvers[static::class] ?? [];
    }
}
