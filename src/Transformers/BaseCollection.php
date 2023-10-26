<?php

namespace CoreFoundation\Transformers;

use Illuminate\Http\Resources\Json\ResourceCollection;

abstract class BaseCollection extends ResourceCollection
{
    public function __construct(mixed $resource = [])
    {
        parent::__construct($resource);
    }
}
