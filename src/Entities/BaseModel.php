<?php

namespace CoreFoundation\Entities;

use CoreFoundation\Traits\ModelFillables;
use Illuminate\Database\Eloquent\Model;

class BaseModel extends Model
{
    use ModelFillables;

    /**
     * Get resolved relations that are binded from service container.
     */
    public static function getBindRelations(): array
    {
        return static::$relationResolvers[static::class] ?? [];
    }
}
