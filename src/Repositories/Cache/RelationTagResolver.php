<?php

namespace CoreFoundation\Repositories\Cache;

use Illuminate\Support\Str;

/**
 * RelationTagResolver
 *
 * Converts eager-loaded relation names into cache invalidation tags.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHY ONLY LOADED RELATIONS                                                   │
 * │                                                                             │
 * │ Previous design used reflection to discover ALL relations on a model and   │
 * │ added all their table names as tags — even for queries that loaded none.   │
 * │                                                                             │
 * │ Problems with that approach:                                                │
 * │  1. Invoking every public model method via reflection is fragile and        │
 * │     expensive — methods may have side effects or throw unexpectedly.        │
 * │  2. A User query that loads no relations gets tagged with 'profiles',       │
 * │     'roles', 'permissions' etc. Any write to those tables busts the User   │
 * │     listing cache unnecessarily.                                            │
 * │  3. Cross-tenant invalidation — a profile write in tenant A busts the      │
 * │     user listing cache in tenant B (since relation tags are unscoped).     │
 * │                                                                             │
 * │ Correct rule: a cache entry can only contain stale relation data if that   │
 * │ relation was actually loaded. Tag only what was loaded.                    │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ TAG FORMAT                                                                  │
 * │                                                                             │
 * │ Relation name → singular snake_case table name (best-effort):              │
 * │   'profile'      → 'profile'                                               │
 * │   'orderItems'   → 'order_item'                                            │
 * │   'order.items'  → ['order', 'item']  (nested: each segment tagged)        │
 * │                                                                             │
 * │ Relation tags are NOT scope-prefixed. A relation table is shared across    │
 * │ all scopes — writing to it may affect any scope that loaded it.            │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
final class RelationTagResolver
{
    /**
     * Convert relation names to cache tags.
     *
     * Only call this with the relations actually passed to ->with() for this
     * query. Do not pass all possible model relations.
     *
     * @param  array<string>  $relations  Relation names e.g. ['profile', 'order.items']
     * @return array<string> Deduplicated table-name tags
     */
    public function resolve(array $relations): array
    {
        if (empty($relations)) {
            return [];
        }

        $tags = [];

        foreach ($relations as $relation) {
            foreach (explode('.', $relation) as $segment) {
                $tags[] = Str::snake(Str::singular($segment));
            }
        }

        return array_unique($tags);
    }
}
