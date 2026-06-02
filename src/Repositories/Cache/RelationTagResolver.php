<?php

namespace CoreFoundation\Repositories\Cache;

use Throwable;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Model;
use CoreFoundation\Entities\Contracts\HasRelationRegistry;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * RelationTagResolver
 *
 * Discovers related model table names for building relation-aware cache tags.
 *
 * When an Order caches its result with relation tags for 'products' and 'users',
 * any change to a Product or User model automatically busts the Order's cache.
 *
 * Relation tags are intentionally NOT scope-prefixed. A product update in any
 * tenant may affect a cached order in any tenant that includes that relation.
 * Scope isolation is applied at the primary (listing/record) tag level only.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ OCTANE SAFETY                                                               │
 * │                                                                             │
 * │ $resolved is static — safe because relation DEFINITIONS are immutable.      │
 * │ In development mode the registry is cleared each call to pick up changes.  │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
final class RelationTagResolver
{
    /**
     * Resolved relation table names per model class.
     * Keyed by model FQCN — safe for static use since definitions are constant.
     *
     * @var array<class-string, array<string>>
     */
    private static array $resolved = [];

    /**
     * Resolve relation-based cache tags for this model.
     *
     * Returns only the RELATION tags — not the primary model tag.
     * The primary (listing / record) tag is built by RepositoryCache.
     *
     * @param  array<string>  $requestedRelations  Relations eager-loaded in this query
     * @return array<string>
     */
    public function resolveRelationTags(Model $model, array $requestedRelations = []): array
    {
        $modelClass = $model::class;

        if (! isset(self::$resolved[$modelClass]) || ! app()->isProduction()) {
            self::$resolved[$modelClass] = $this->discoverRelationTables($model);
        }

        $tags = self::$resolved[$modelClass];
        $tags = array_merge($tags, $this->resolveRequestedRelationTags($requestedRelations));

        return array_unique($tags);
    }

    /**
     * Clear the resolved cache for a model class.
     * Call in tests when relations are added dynamically.
     */
    public static function clear(string $modelClass): void
    {
        unset(self::$resolved[$modelClass]);
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Discover relation table names via Reflection.
     *
     * Inspects public, no-parameter, non-magic methods that return Eloquent Relation
     * instances. Also inspects externally bound relations from ModelRelatable::addRelation().
     *
     * @return array<string>
     */
    private function discoverRelationTables(Model $model): array
    {
        $tables = [];
        $reflection = new ReflectionClass($model);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (
                $method->class !== $model::class
                || $method->getNumberOfParameters() > 0
                || str_starts_with($method->getName(), '__')
            ) {
                continue;
            }

            try {
                $result = $method->invoke($model);

                if ($result instanceof Relation) {
                    $tables[] = $result->getRelated()->getTable();
                }
            } catch (Throwable) {
                // Not a relation method — skip
            }
        }

        if ($model instanceof HasRelationRegistry) {
            foreach ($model::getBindRelations() as $closure) {
                try {
                    $result = $closure->call($model, $model);

                    if ($result instanceof Relation) {
                        $tables[] = $result->getRelated()->getTable();
                    }
                } catch (Throwable) {
                    // Skip
                }
            }
        }

        return array_unique($tables);
    }

    /**
     * Convert dot-notation relation names to table name tags.
     * 'order.items' → ['order', 'item']
     *
     * @param  array<string>  $relations
     * @return array<string>
     */
    private function resolveRequestedRelationTags(array $relations): array
    {
        $tags = [];

        foreach ($relations as $relation) {
            foreach (explode('.', $relation) as $segment) {
                $tags[] = Str::snake(Str::singular($segment));
            }
        }

        return $tags;
    }
}
