<?php

namespace CoreFoundation\Entities\Contracts;

/**
 * HasRelationRegistry
 *
 * Marks a model as supporting externally bound relations registered via
 * ModelRelatable::addRelation(). Implemented by BaseModel.
 *
 * When a repository model does not implement this interface, RelationTagResolver
 * skips the registry and falls back to Reflection-only relation discovery.
 */
interface HasRelationRegistry
{
    /** @return array<string, \Closure> */
    public static function getBindRelations(): array;
}
