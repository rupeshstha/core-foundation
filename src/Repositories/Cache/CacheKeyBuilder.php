<?php

namespace CoreFoundation\Repositories\Cache;

use CoreFoundation\Entities\BaseModel;

/**
 * CacheKeyBuilder
 *
 * Builds deterministic, Octane-safe cache keys for repository queries.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ KEY FORMAT                                                                  │
 * │                                                                             │
 * │ Scoped:   "{scope}:{table}:{method}:{hash}"                                 │
 * │ Global:   "{table}:{method}:{hash}"          (backward-compatible)         │
 * │                                                                             │
 * │ Hash = md5 of json-encoded payload (model, method, filters, relations,     │
 * │ columns, extra). Same parameters → same key, regardless of call stack.     │
 * │                                                                             │
 * │ TENANT ISOLATION EXAMPLE                                                    │
 * │                                                                             │
 * │ Tenant 1  →  tenant:1:products:fetchAll:abc123                              │
 * │ Tenant 2  →  tenant:2:products:fetchAll:abc123   ← same query, diff key   │
 * │ No scope  →  products:fetchAll:abc123             (global, no isolation)   │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
final class CacheKeyBuilder
{
    /**
     * Build a cache key for a repository query.
     *
     * @param  BaseModel  $model  The model being queried (schema reference only)
     * @param  string  $method  Repository method name e.g. 'fetchAll', 'fetchById'
     * @param  array  $filters  Applied filter parameters
     * @param  array  $relations  Eager-loaded relation names
     * @param  array  $columns  Selected columns
     * @param  array  $extra  Additional discriminators (record ID, pagination params)
     * @param  CacheScope|null  $scope  Isolation boundary — tenant, user, etc.
     */
    public function build(
        BaseModel $model,
        string $method,
        array $filters = [],
        array $relations = [],
        array $columns = [],
        array $extra = [],
        ?CacheScope $scope = null,
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
        $base = sprintf('%s:%s:%s', $model->getTable(), $method, $hash);

        return $scope
            ? sprintf('%s:%s', $scope->prefix(), $base)
            : $base;
    }

    /**
     * Build the record-level tag identifier for a specific record ID.
     *
     * Not used as a cache key — used as a cache TAG.
     * All cache entries tagged with this value are flushed when that record
     * is updated or deleted, without touching any other record's cache.
     *
     * @param  int|string  $id  The record's primary key
     * @param  CacheScope|null  $scope  Must match the scope used at write time
     */
    public function buildRecordTag(
        BaseModel $model,
        int|string $id,
        ?CacheScope $scope = null,
    ): string {
        $base = sprintf('%s:record:%s', $model->getTable(), $id);

        return $scope
            ? sprintf('%s:%s', $scope->prefix(), $base)
            : $base;
    }

    /**
     * Build the listing tag for a model.
     *
     * All fetchAll / paginated queries are tagged with this value.
     * Flushed when any record in this model+scope is created, updated, or deleted.
     *
     * @param  CacheScope|null  $scope  Must match the scope used at write time
     */
    public function buildListingTag(BaseModel $model, ?CacheScope $scope = null): string
    {
        $base = sprintf('%s:listing', $model->getTable());

        return $scope
            ? sprintf('%s:%s', $scope->prefix(), $base)
            : $base;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Sort keys recursively so parameter order does not affect the hash.
     * ['b' => 1, 'a' => 2] and ['a' => 2, 'b' => 1] produce the same hash.
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
