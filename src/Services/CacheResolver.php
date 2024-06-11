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
use Illuminate\Database\Eloquent\Model;

class CacheResolver
{
    use HasCacheable;

    protected Model $model;

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
    public function getModelRelationships(): array
    {
        $relationships = [];
        $modelMethods = (new ReflectionClass($this->model))->getMethods(ReflectionMethod::IS_PUBLIC);
        $bindRelations = $this->model::getBindRelations();

        // Checks relations if model class has relations on it's own class
        foreach ($modelMethods as $method) {
            if (
                $method->class != $this->model::class
            ) {
                continue;
            }

            $return = $method->invoke($this->model);
            if ($return instanceof Relation) {
                $relationships[$method->getName()] = [
                    "name" => $method->getName(),
                    "relates_to" => $return->getRelated(),
                ];
            }
        }

        // Checks relations that are resolved from service provider
        foreach ($bindRelations as $method => $bindRelation) {
            $executeClosure = $bindRelation->call($this->model, $this->model);
            if ($executeClosure instanceof Relation) {
                $relationships[$method] = [
                    "name" => $method,
                    "relates_to" => $executeClosure->getRelated(),
                ];
            }
        }

        return $relationships;
    }
}
