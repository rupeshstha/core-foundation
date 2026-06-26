<?php

namespace CoreFoundation\Repositories\Cache;

use Illuminate\Database\Eloquent\Model;

/**
 * RelationTagResolver
 *
 * Converts eager-loaded relation names into cache invalidation tags by
 * resolving the *actual* related model — not by guessing a table name from
 * the relation's string name.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHY THE ACTUAL RELATION, NOT A STRING GUESS                                 │
 * │                                                                             │
 * │ An earlier version derived the tag by singularising the relation name      │
 * │ (e.g. 'comments' → 'comment'). That string can never equal the real table  │
 * │ tag a write busts (CacheKeyBuilder::buildRelatedTag() uses the model's      │
 * │ actual getTable(), which is plural by Eloquent convention) — the           │
 * │ invalidation silently never fires. Calling the relation method and reading │
 * │ getRelated()->getTable() gets the real table every time, for the cost of   │
 * │ one cheap, no-query object construction per relation actually requested.   │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHY ONLY LOADED RELATIONS                                                   │
 * │                                                                             │
 * │ An earlier design discovered ALL relations on a model via reflection and    │
 * │ tagged every query with all of them, even when none were loaded. That      │
 * │ over-tags every cache entry and busts listings on unrelated writes.        │
 * │ Correct rule: a cache entry can only contain stale relation data if that   │
 * │ relation was actually loaded. Only resolve relations the caller requested. │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ TAG FORMAT                                                                  │
 * │                                                                             │
 * │ Each segment of a relation path produces a "related" tag for the actual    │
 * │ related model, scope-prefixed to match the model that owns it:             │
 * │   'comments'      → CacheKeyBuilder::buildRelatedTag(Comment, $scope)      │
 * │   'order.items'   → tags for both Order and Item (every segment in the     │
 * │                      chain may surface in the eager-loaded result)         │
 * │                                                                             │
 * │ Scope-prefixed using the SAME $scope as the query being tagged — a         │
 * │ tenant-scoped query's relation tags never collide with another tenant's.   │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
final class RelationTagResolver
{
    public function __construct(private readonly CacheKeyBuilder $keyBuilder) {}

    /**
     * Convert relation names to cache tags by resolving each one against the
     * owning model.
     *
     * Only call this with the relations actually passed to ->with() for this
     * query. Do not pass all possible model relations.
     *
     * @param  Model  $model  The model the relations are being loaded from
     * @param  array<string>  $relations  Relation names e.g. ['profile', 'order.items']
     * @param  CacheScope|null  $scope  Must match the scope used for the query being tagged
     * @return array<string> Deduplicated related-model tags
     */
    public function resolve(Model $model, array $relations, ?CacheScope $scope = null): array
    {
        if (empty($relations)) {
            return [];
        }

        $tags = [];

        foreach ($relations as $relation) {
            $current = $model;

            foreach (explode('.', $relation) as $segment) {
                $related = $current->{$segment}()->getRelated();
                $tags[] = $this->keyBuilder->buildRelatedTag($related, $scope);
                $current = $related;
            }
        }

        return array_unique($tags);
    }
}
