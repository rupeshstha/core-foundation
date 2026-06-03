<?php

namespace CoreFoundation\Entities\Contracts;

/**
 * HasSearchableColumns
 *
 * Marks a model as having a declared set of filterable/searchable columns.
 * Implemented by BaseModel via ModelSearchable — also implementable by any
 * model that does not extend BaseModel.
 *
 * When a repository model does not implement this interface, BaseRepository
 * falls back to an empty searchable set (no filter columns exposed).
 */
interface HasSearchableColumns
{
    /** @param array<string> $columns */
    public static function addSearchable(array $columns): void;

    /** @return array<string> */
    public static function getSearchable(): array;
}
