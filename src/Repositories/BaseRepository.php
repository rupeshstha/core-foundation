<?php

namespace Rupeshstha\CoreFoundation\Repositories;

use Illuminate\Database\Eloquent\Model;
use Rupeshstha\CoreFoundation\Contracts\BaseRepositoryInterface;

final class BaseRepository implements BaseRepositoryInterface
{
    public function __construct(
        protected Model $model
    ) {
        
    }
}
