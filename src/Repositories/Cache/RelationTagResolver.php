<?php

namespace CoreFoundation\Repositories\Cache;

use Throwable;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Str;
use CoreFoundation\Entities\BaseModel;
use Illuminate\Database\Eloquent\Relations\Relation;

/**
 * RelationTagResolver
 *
 * Discovers all Eloquent relation table names for a model via Reflection.
 * Used to build relation-aware cache tags so that when a related model
 * changes, the parent's cache entries are also invalidated.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ OCTANE SAFETY                                                               │
 * │                                                                             │
 * │ The resolved relation registry ($resolved) is a static property.           │
 * │ In production, resolved relations are cached across requests — correct,     │
 * │ because model relation definitions don't change at runtime.                 │
 * │                                                                             │
 * │ In non-production, the registry is cleared on each resolution so new       │
 * │ relations added during development are always picked up.                   │
 * │                                                                             │
 * │ Unlike the original RepositoryCacheResolver, this class does NOT use       │
 * │ debug_backtrace() and does NOT invoke relation closures with side effects.  │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
final class RelationTagResolver
{
    /**
     * Resolved relation table names per model class.
     * Keyed by model FQCN — safe for static use since relation definitions
     * are constant for the lifetime of a process.
     *
     * @var array<class-string, array<string>>
     */
    private static array $resolved = [];

    /**
     * Resolve cache tags for a model — model table + all related table names.
     *
     * @param  array<string>  $requestedRelations  Relations requested in this query
     * @return array<string>
     */
    public function resolve(BaseModel $model, array $requestedRelations = []): array
    {
        $modelClass = $model::class;

        if (! isset(self::$resolved[$modelClass]) || ! app()->isProduction()) {
            self::$resolved[$modelClass] = $this->discoverRelationTables($model);
        }

        // Base tag: the model's own table
        $tags = [$model->getTable()];

        // Add all discovered relation tables — so writes to related models bust this cache
        $tags = array_merge($tags, self::$resolved[$modelClass]);

        // Add tags from explicitly requested relations (handles dot-notation)
        $tags = array_merge($tags, $this->resolveRequestedRelationTags($requestedRelations));

        return array_unique($tags);
    }

    /**
     * Clear the resolved cache for a model class.
     * Call when a new relation is dynamically added (e.g. in tests).
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
     * Inspects public no-parameter methods that return Eloquent Relation instances.
     * Also inspects externally bound relations registered via ModelRelatable::addRelation().
     *
     * @return array<string>
     */
    private function discoverRelationTables(BaseModel $model): array
    {
        $tables = [];

        // Inspect native model relation methods
        $reflection = new ReflectionClass($model);

        foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            // Skip: inherited methods, methods with parameters, magic methods
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
                // Method threw — not a relation method, skip silently
            }
        }

        // Inspect externally bound relations (registered via ModelRelatable::addRelation())
        foreach ($model::getBindRelations() as $name => $closure) {
            try {
                $result = $closure->call($model, $model);

                if ($result instanceof Relation) {
                    $tables[] = $result->getRelated()->getTable();
                }
            } catch (Throwable) {
                // Closure threw — skip silently
            }
        }

        return array_unique($tables);
    }

    /**
     * Convert dot-notation relation names to table name tags.
     * 'user.profile' → ['user', 'profile'] (singular snake_case)
     *
     * @param  array<string>  $relations
     * @return array<string>
     */
    private function resolveRequestedRelationTags(array $relations): array
    {
        $tags = [];

        foreach ($relations as $relation) {
            if (is_string($relation)) {
                foreach (explode('.', $relation) as $segment) {
                    $tags[] = Str::snake(
                        Str::singular($segment)
                    );
                }
            }
        }

        return $tags;
    }
}
