<?php

namespace CoreFoundation\Repositories;

use Illuminate\Foundation\Application;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Collection;
use CoreFoundation\Contracts\BaseRepositoryInterface;
use CoreFoundation\Exceptions\ModelNotInstantiableException;
use CoreFoundation\Services\CacheManager;
use CoreFoundation\Services\ModelFilterable;
use CoreFoundation\Traits\HasEvent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Arr;

/**
 * TODO:: singleton repository instances.
 */
abstract class BaseRepository implements BaseRepositoryInterface
{
    use HasEvent;

    protected Model $model;

    protected string $tableName;

    protected int $perPage;
    protected bool $isCached;
    protected int $cacheTTl; // 60 min
    protected array $cacheAllowedMethods = [];
    protected array $coreConfig = [];

    public function __construct(
        protected Application $app,
        protected CacheManager $cacheManager,
        protected ModelFilterable $modelFilterable
    ) {
        $this->register();
    }

    /**
     * This method will set the repository model.
     *
     * @return string
     */
    abstract protected function setModel(): string;

    /**
     * Registers model repository.
     *
     * @return void
     */
    private function register(): void
    {
        $modelInstance = !isset($this->model)
            ? $this->app->make($this->setModel())
            : null;

        throw_unless(
            condition: $modelInstance instanceof Model,
            exception: new ModelNotInstantiableException(
                message: "Class {$this->setModel()} must be an instance of Illuminate\\Database\\Eloquent\\Model"
            )
        );

        $this->model = $modelInstance;
        $this->tableName = $this->model->getTable();
        $this->cacheManager->setModel($this->model);
        $this->coreConfig = config("core_foundation");

        $this->cacheAllowedMethods = Arr::get($this->coreConfig, "cache.cache_repository_methods");
    }

    /**
     * In every repository base function call before method will be called.
     *
     * @return void
     */
    protected function before(): void
    {
    }

    /**
     * In every successful repository base function call after method will be called.
     *
     * @return void
     */
    protected function after(): void
    {
    }

    public function fetchAll(array $filterable = [], array $relationship = []): Collection|Paginator
    {
        $this->eventDispatch(
            eventKey: "fetch-all.before",
            data: [
                "request" => $filterable,
                "relationship" => $relationship,
            ]
        );

        $fetched = $this->cacheManager->make(
            relates: $relationship,
            callback: function () use ($filterable, $relationship) {
                $rows = $this->model::query()
                    ->when($relationship, function (Builder $query) use ($relationship) {
                        $query->with($relationship);
                    });
                return $this->modelFilterable->getFiltered($rows, $filterable, $relationship);
            },
            isCached: in_array(__FUNCTION__, $this->cacheAllowedMethods),
            identifier: [$filterable, $relationship],
        );

        $this->eventDispatch(
            eventKey: "fetch-all.after",
            data: $fetched
        );

        return $fetched;
    }
}
