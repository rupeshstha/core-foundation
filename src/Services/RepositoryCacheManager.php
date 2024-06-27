<?php

namespace CoreFoundation\Services;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use CoreFoundation\Entities\BaseModel;

class RepositoryCacheManager extends RepositoryCacheResolver
{
    private readonly bool $isEnable;
    protected BaseModel $model;

    public function __construct()
    {
        $this->isEnable = config("core_foundation.cache.global", true);
    }

    public function setModel(BaseModel $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): BaseModel
    {
        return $this->model;
    }

    /**
     * Set cache status
     *
     * @param boolean $enable
     *
     * @return self
     */
    public function setCacheStatus(bool $enable = true): self
    {
        $this->isEnable = $enable;

        return $this;
    }

    public function make(
        array $relates = [],
        Closure $callback = null,
        bool $isCached = true,
        mixed ...$identifier
    ): mixed {
        if (!$this->isEnable || !$isCached) {
            return $callback();
        }

        $modelRelations = $this->getModelRelationships($this->model);
        $relates = $this->resolveRelationKeys($relates);
        $relationalKeys = array_unique(array_merge($modelRelations, $relates));

        $backTraceMethod = Arr::last(debug_backtrace(limit: 4));

        $identifier[] = [
            "parent_method_name" =>  $backTraceMethod["function"],
            "argument" => $backTraceMethod["args"],
            "relation_keys" => $relationalKeys,
        ];

        $hash = md5(json_encode($identifier));
        $tags = array_merge([$this->model->getTable()], $relationalKeys);

        $data = $this->storeTagCache(
            tags: $tags,
            key: $hash,
            closure: $callback
        );

        return $data;
    }

    public function flushAllCache(): void
    {
        $taggable = $this->model->getTable();

        $this->flushTagCache([$taggable, Str::snake(Str::singular($taggable))]);

        Log::info(
            message: "Cache_Invalidate:",
            context: [
                "triggered_from" => $taggable,
            ]
        );
    }
}
