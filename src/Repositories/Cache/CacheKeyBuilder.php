<?php

namespace CoreFoundation\Repositories\Cache;

use CoreFoundation\Entities\BaseModel;

/**
 * CacheKeyBuilder
 *
 * Builds deterministic, Octane-safe cache keys for repository queries.
 * Replaces the debug_backtrace() approach in the original RepositoryCacheManager.
 *
 * Keys are built from explicit parameters — no runtime introspection.
 * The same parameters always produce the same key, regardless of call stack depth.
 *
 * Key format: "{model_class}:{method}:{hash}"
 * Where hash = md5 of json-encoded [filters, relations, columns, extra]
 */
final class CacheKeyBuilder
{
    /**
     * Build a cache key for a repository query.
     *
     * @param  BaseModel  $model  The model being queried
     * @param  string  $method  Repository method name e.g. 'fetchAll', 'fetchById'
     * @param  array  $filters  Filter parameters
     * @param  array  $relations  Eager-load relation names
     * @param  array  $columns  Selected columns
     * @param  array  $extra  Any additional discriminators (e.g. record ID)
     */
    public function build(
        BaseModel $model,
        string $method,
        array $filters = [],
        array $relations = [],
        array $columns = [],
        array $extra = [],
    ): string {
        $payload = [
            'model' => $model::class,
            'method' => $method,
            'filters' => $this->normalise($filters),
            'relations' => $this->normalise($relations),
            'columns' => $this->normalise($columns),
            'extra' => $this->normalise($extra),
        ];

        $hash = md5(json_encode($payload, JSON_THROW_ON_ERROR));

        return sprintf('%s:%s:%s', $model->getTable(), $method, $hash);
    }

    /**
     * Build a granular cache key for a specific record.
     * Used for update invalidation — only invalidates keys that included this ID.
     */
    public function buildForRecord(BaseModel $model, int|string $id): string
    {
        return sprintf('%s:record:%s', $model->getTable(), $id);
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Normalise an array for consistent hashing — sort keys recursively.
     * ['b' => 1, 'a' => 2] and ['a' => 2, 'b' => 1] must produce the same hash.
     */
    private function normalise(array $data): array
    {
        ksort($data);

        return array_map(
            fn ($value) => is_array($value) ? $this->normalise($value) : $value,
            $data,
        );
    }
}
