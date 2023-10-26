<?php

namespace CoreFoundation\Services;

use Closure;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;

class CacheManager extends CacheResolver
{
    private readonly bool $isEnable;
    private Model $model;

    public function __construct()
    {
        $this->isEnable = config("core_foundation.cache.status", true);
    }

    public function setModel(Model $model): self
    {
        $this->model = $model;

        return $this;
    }

    public function getModel(): Model
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

        $relationalKeys = $this->resolveRelationKeys($relates);
        $backTraceMethod = Arr::last(debug_backtrace(DEBUG_BACKTRACE_PROVIDE_OBJECT, 4));
        $identifier[] = [
            "parent_method_name" =>  $backTraceMethod["function"],
            "argument" => $backTraceMethod["args"]
        ];

        $hash = $this->generateHash($identifier, $relationalKeys);
        $tags = $this->generateCacheTags($this->model->getTable(), $relationalKeys);

        $data = $this->storeTagCache(
            tags: $tags,
            key: $hash,
            closure: $callback
        );

        return $data;
    }

    public function flushAllCache(): void
    {
        $taggable = Str::snake(Str::singular($this->model->getTable()));
        $this->flushTagCache([$taggable]);

        Log::info(
            message: "Cache_Invalidate:",
            context: [
                "triggered_from" => $taggable,
            ]
        );
    }
}
