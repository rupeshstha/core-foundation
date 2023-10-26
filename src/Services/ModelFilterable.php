<?php

namespace CoreFoundation\Services;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use CoreFoundation\Http\Requests\ValidateFilterableRequest;

class ModelFilterable
{
    public function __construct(
        protected ValidateFilterableRequest $validateFilterableRequest
    ) {
    }

    public function getFiltered(Builder $rows, array $filterable = [], array $relationship = []): object
    {
        $data = $this->validateFilterableRequest->validated();
        dd($data);
        $rows->paginate();
        return $rows;
    }
}
