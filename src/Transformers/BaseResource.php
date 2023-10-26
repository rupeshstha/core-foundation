<?php

namespace CoreFoundation\Transformers;

use Illuminate\Http\Resources\Json\JsonResource;

class BaseResource extends JsonResource
{
    public function __construct(mixed $resource = [])
    {
        parent::__construct($resource);
    }
}
