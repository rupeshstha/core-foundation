<?php

namespace CoreFoundation\Http\Requests;

class ValidateFilterableRequest extends BaseRequest
{
    protected function baseRules(): array
    {
        return [
            'per_page' => 'sometimes|numeric',
            'page' => 'sometimes|numeric',
            'no_paginate' => 'sometimes|boolean',
            'sort_by' => 'sometimes',
            'sort_order' => 'sometimes|in:asc,desc',
            'q' => 'sometimes|string',
            'filters' => 'sometimes|array',
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'per_page.numeric' => 'Per page count must be a number.',
            'page.numeric' => 'Page must be a number.',
            'sort_order.in' => 'Order must be \'asc\' or \'desc\'.',
            'q.string' => 'Search query must be a string.',
            'filter_by.string' => 'Filter by must be a string.',
        ];
    }
}
