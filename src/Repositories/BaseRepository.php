<?php

namespace CoreFoundation\Repositories;

use Illuminate\Support\Arr;
use CoreFoundation\Traits\HasEvent;
use CoreFoundation\Entities\BaseModel;
use Illuminate\Foundation\Application;
use Illuminate\Database\Eloquent\Builder;
use CoreFoundation\Services\ModelFilterable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Contracts\Pagination\Paginator;
use CoreFoundation\Services\RepositoryCacheManager;
use CoreFoundation\Contracts\BaseRepositoryInterface;
use CoreFoundation\Exceptions\ModelNotInstantiableException;

abstract class BaseRepository implements BaseRepositoryInterface
{
    use HasEvent;

    protected BaseModel $model;

    protected array $coreConfig = [];

    protected array $cacheAllowedMethods = [];

    public function __construct(
        protected Application $app,
        protected ModelFilterable $modelFilterable,
        protected RepositoryCacheManager $cacheManager,
    ) {
        $this->register();
    }

    /**
     * This method will set the repository model.
     */
    abstract protected function setModel(): string;

    /**
     * Registers model repository.
     */
    private function register(): void
    {
        /**
         * Singleton model instance inside service container.
         * Reduce instantiation of same model object.
         */
        $this->app->singleton($this->setModel(), $this->setModel());

        $modelInstance = $this->app->make($this->setModel());

        throw_unless(
            condition: $modelInstance instanceof BaseModel,
            exception: new ModelNotInstantiableException(
                message: "Class {$this->setModel()} must be an instance of CoreFoundation\\Entities\\BaseModel"
            )
        );

        $this->model = $modelInstance;
        $this->cacheManager->setModel($this->model);
        $this->coreConfig = config('core_foundation');

        $this->cacheAllowedMethods = Arr::get($this->coreConfig, 'cache.cache_repository_methods');
        $this->eventPrefix = $this->model->getTable();
        $this->eventDispatch = true;
    }

    public function fetchAll(
        array $filterable = [],
        array $relationship = [],
        array $columns = ['*']
    ): Collection|Paginator {
        $this->eventDispatch(
            eventKey: 'fetch-all.before',
            data: [
                'request' => $filterable,
                'relationship' => $relationship,
            ]
        );

        $fetched = $this->cacheManager->make(
            relates: $relationship,
            callback: function () use ($filterable, $relationship, $columns) {
                $rows = $this->model::select($columns)
                    ->when($relationship, function (Builder $query) use ($relationship) {
                        $query->with($relationship);
                    });

                return $this->modelFilterable
                    ->setModel($this->model)
                    ->getFiltered($rows, $filterable, $relationship);
            },
            isCached: in_array(__FUNCTION__, $this->cacheAllowedMethods, true),
            identifier: [$filterable, $relationship],
        );

        $this->eventDispatch(
            eventKey: 'fetch-all.after',
            data: $fetched
        );

        return $fetched;
    }
}
