<?php

namespace CoreFoundation\Repositories;

use Illuminate\Support\Arr;
use CoreFoundation\Traits\HasEvent;
use Illuminate\Pagination\Paginator;
use CoreFoundation\Entities\BaseModel;
use Illuminate\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use CoreFoundation\Services\ModelFilterable;
use Illuminate\Database\Eloquent\Collection;
use CoreFoundation\Manipulators\ObjectMutable;
use CoreFoundation\Services\RepositoryCacheManager;
use CoreFoundation\Contracts\BaseRepositoryInterface;
use CoreFoundation\Exceptions\ModelNotInstantiableException;

abstract class BaseRepository implements BaseRepositoryInterface
{
    use HasEvent;

    protected BaseModel $model;

    protected string $tableName;

    protected int $perPage;
    protected bool $isCached;
    protected int $cacheTTl;
    protected array $cacheAllowedMethods = [];
    protected array $coreConfig = [];

    public function __construct(
        protected Application $app,
        protected RepositoryCacheManager $cacheManager,
        protected ModelFilterable $modelFilterable,
        protected ObjectMutable $objectMutable,
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
            condition: $modelInstance instanceof BaseModel,
            exception: new ModelNotInstantiableException(
                message: "Class {$this->setModel()} must be an instance of CoreFoundation\\Entities\\BaseModel"
            )
        );

        $this->model = $modelInstance;
        $this->tableName = $this->model->getTable();
        $this->cacheManager->setModel($this->model);
        $this->coreConfig = config("core_foundation");

        $this->cacheAllowedMethods = Arr::get($this->coreConfig, "cache.cache_repository_methods");
        $this->eventPrefix = class_basename($this);
        $this->eventDispatch = true;
    }

    public function fetchAll(
        array $filterable = [],
        array $relationship = [],
        array $columns = ['*']
    ): Collection|Paginator {
        $this->eventDispatch(
            eventKey: "fetch-all.before",
            data: [
                "request" => $filterable,
                "relationship" => $relationship,
            ]
        );

        $fetched = $this->cacheManager->make(
            relates: $relationship,
            callback: function () use ($filterable, $relationship, $columns) {
                $rows = $this->model::query()
                    ->select($columns)
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
