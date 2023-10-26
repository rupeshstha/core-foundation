<?php

namespace CoreFoundation\Services;

use Closure;
use Exception;
use ReflectionClass;
use ReflectionMethod;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\Relation;
use CoreFoundation\Traits\HasCacheable;

class CacheResolver
{
    use HasCacheable;

    public function generateHash(mixed ...$identifiers): string
    {
        return md5(json_encode($identifiers));
    }

    /**
     * Generate cache tags.
     *
     * @param string $triggeredKey
     * @param array $taggableKeys
     *
     * @return array
     */
    public function generateCacheTags(string $triggeredKey, array $taggableKeys): array
    {
        $triggeredKey = [Str::snake(Str::singular($triggeredKey))];

        $data = array_merge($triggeredKey, $taggableKeys);

        return $data;
    }

    public function resolveRelationKeys(array $relations): array
    {
        $data = [];
        foreach ($relations as $relation) {
            if ($relation instanceof Closure) {
                continue;
            }
            if (Str::contains($relation, ".")) {
                $nestedRelationKeys = explode(".", $relation);
                $tags = array_map(function ($nestedRelationKey) {
                    return Str::snake(Str::singular($nestedRelationKey));
                }, $nestedRelationKeys);
            } else {
                $tags = Str::snake(Str::singular($relation));
            }

            $data[] = $tags;
        }

        $data = Arr::flatten($data);

        return $data;
    }

    /**
     * Returns model relation type and related model class.
     *
     * @param object $model
     *
     * @return array
     */
    public function getModelRelationships(object $model): array
    {
        $relationships = [];
        $modelMethods = (new ReflectionClass($model))->getMethods(ReflectionMethod::IS_PUBLIC);

        foreach ($modelMethods as $method) {
            if (
                $method->class != get_class($model)
                || !empty($method->getParameters())
                || $method->getName() == __FUNCTION__
            ) {
                continue;
            }

            try {
                $return = $method->invoke($model);

                if ($return instanceof Relation) {
                    $relationships[] = [
                        $method->getName(),
                        Str::snake(Str::singular($method->getName())),
                    ];
                }
            } catch (Exception $exception) {
                // do nothing
            }
        }

        $relationships = array_unique(Arr::flatten($relationships));
        return $relationships;
    }
}
