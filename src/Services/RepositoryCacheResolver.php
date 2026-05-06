<?php

namespace CoreFoundation\Services;

use Closure;
use CoreFoundation\Entities\BaseModel;
use CoreFoundation\Traits\HasCacheable;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ReflectionClass;
use ReflectionMethod;

class RepositoryCacheResolver
{
    use HasCacheable;

    protected BaseModel $model;

    protected static array $resolvedRelationKeys = [];

    protected static array $cacheModelInstances = []; // TODO: reduce model object initializations. Maintain singleton when searching relation through Reflection and executing Closure.

    /**
     * Search all the relation binded to a model and sets to $resolvedRelationKeys property.
     */
    private function setModelRelationship(): void
    {
        $relationships = [];
        $relationshipTableNames = [];
        $modelMethods = (new ReflectionClass($this->model))->getMethods(ReflectionMethod::IS_PUBLIC);
        $bindRelations = $this->model::getBindRelations();

        // Checks relations if model class has relations on it's own class.
        foreach ($modelMethods as $method) {
            if (
                $method->class !== $this->model::class
                || ! empty($method->getParameters())
                || $method->getName() === __FUNCTION__
            ) {
                continue;
            }

            $return = $method->invoke($this->model);
            if ($return instanceof Relation) {
                $relatesTo = $return->getRelated();
                $relationships[$method->getName()] = [
                    'name' => $method->getName(),
                    'table_name' => $relatesTo->getTable(),
                    'relates_to' => $relatesTo,
                ];

                $relationshipTableNames[] = $relatesTo->getTable();
            }
        }

        // Checks relations that are resolved from service provider.
        foreach ($bindRelations as $method => $bindRelation) {
            $executeClosure = $bindRelation->call($this->model, $this->model);
            if ($executeClosure instanceof Relation) {
                $relatesTo = $executeClosure->getRelated();
                $relationships[$method] = [
                    'name' => $method,
                    'table_name' => $relatesTo->getTable(),
                    'relates_to' => $relatesTo,
                ];

                $relationshipTableNames[] = $relatesTo->getTable();
            }
        }

        static::$resolvedRelationKeys[$this->model::class] = array_unique($relationshipTableNames);
    }

    /**
     * Returns model defined relation table names.
     */
    public function getModelRelationships(): array
    {
        if (
            ! isset(static::$resolvedRelationKeys[$this->model::class])
            || ! app()->isProduction() // When app is in development mode you need to check model relations every time.
        ) {
            $this->setModelRelationship();
        }

        $modelRelationships = static::$resolvedRelationKeys[$this->model::class];

        return $modelRelationships;
    }

    public function resolveRelationKeys(array $relations): array
    {
        $passedRelationTags = [];
        foreach ($relations as $key => $relation) {
            if ($relation instanceof Closure) {
                $relation = $key;
            }

            $nestedRelationKeys = explode('.', $relation);
            $relationTags = array_map(
                callback: fn ($nestedRelationKey) => Str::snake(Str::singular($nestedRelationKey)),
                array: $nestedRelationKeys
            );

            $passedRelationTags[] = $relationTags;
        }

        $data = Arr::flatten($passedRelationTags);

        return $data;
    }
}
