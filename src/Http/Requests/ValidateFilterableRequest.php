<?php

namespace CoreFoundation\Http\Requests;

class ValidateFilterableRequest extends BaseRequest
{
    public function rules(): array
    {
        return [
            "per_page" => "sometimes|numeric",
            "page" => "sometimes|numeric",
            "no_paginate" => "sometimes|boolean",
            "sort_by" => "sometimes",
            "sort_order" => "sometimes|in:asc,desc",
            "search" => "sometimes|string",
            "filter" => "sometimes|array",
            "filter.*.filter_by" => "required|string",
            "filter.*.value" => "required_with:filter.*.filter_by|string",
            "get_trashed" => "sometimes|boolean",
            "get_only_trashed" => "sometimes|boolean",
        ];
    }

    public function messages(): array
    {
        return [
            "per_page.numeric" => "Per page count must be a number.",
            "page.numeric" => "Page must be a number.",
            "sort_order.in" => "Order must be 'asc' or 'desc'.",
            "search.string" => "Search query must be a string.",
            "filter_by.string" => "Filter by must be a string.",
        ];
    }
}
