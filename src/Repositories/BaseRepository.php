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

abstract class BaseRepository implements BaseRepositoryInterface
{
    use HasEvent;

    protected Model $model;

    protected string $tableName;

    protected int $perPage = 25;
    protected bool $isCached = true;
    protected int $cacheTTl = 60; // 60 min

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
     * It is necessary to initialize this function before any repository action.
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
            exception: new ModelNotInstantiableException("Class {$this->setModel()} must be an instance of Illuminate\\Database\\Eloquent\\Model")
        );

        $this->model = $modelInstance;
        $this->tableName = $this->model->getTable();
        $this->cacheManager->setModel($this->model);
    }

    /**
     * Boot method will always
     *
     * @return void
     */
    protected function boot(): void
    {
    }

    public function fetchAll(array $filterable = [], array $relationship = []): Collection|Paginator
    {
        $this->boot();

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
            identifier: [$filterable, $relationship]
        );

        $this->eventDispatch(
            eventKey: "fetch-all.after",
            data: $fetched
        );

        return $fetched;
    }


    /**
     * Get object or redirect to 404.
     *
     * @param  mixed $id
     * @param  mixed $with
     *
     * @return object
     */
    public function fetch(int|string $id, array $with = []): object
    {
        $this->eventDispatch(
            eventKey: "fetch-single.before",
            data: [
                "id" => $id,
                "with" => $with,
            ]
        );

        $rows = $this->model::query();
        if ($with != []) {
            $rows = $rows->with($with);
        }

        $fetched = $this->cacheManager->make(
            relates: $with,
            callback: function () use ($rows, $id) {
                return $rows->findOrFail($id);
            },
            identifier: [$id, $with]
        );

        $this->eventDispatch(
            eventKey: "fetch-single.after",
            data: $fetched
        );

        return $fetched;
    }

   /**
    * Fetch by specific column
    *
    * @param string $column
    * @param integer|string $value
    * @param array $with
    * @return object
    */
    public function fetchBy(string $column, int|string $value, array $with = []): object
    {
        $this->eventDispatch(
            eventKey: "fetch-by-column-single.before",
            data: [
                "column" => $column,
                "value" => $value,
                "with" => $with,
            ]
        );

        $fetched = $this->cacheManager->make(
            relates: $with,
            callback: function () use ($with, $column, $value) {
                $rows = $this->model::query();
                if ($with != []) {
                    $rows = $rows->with($with);
                }

                return $rows->where($column, $value)->firstOrFail();
            },
            identifier: [$column, $value, $with]
        );

        $this->eventDispatch(
            eventKey: "fetch-by-column-single.after",
            data: $fetched
        );

        return $fetched;
    }

    /**
     * Update or Create
     *
     * @param  array $match
     * @param  array $data
     *
     * @return object
     */
    public function updateOrStore(array $match, array $data): object
    {
        $this->eventDispatch(
            eventKey: "update-or-store.before",
            data: [
                "match" => $match,
                "data" => $data,
            ]
        );

        $updated = $this->model->updateOrCreate($match, $data);

        $this->eventDispatch(
            eventKey: "update-or-store.after",
            data: $updated
        );

        $this->cacheManager->flushAllCache();

        return $updated;
    }

    /**
     * fetchOrStore
     *
     * @param  array $data
     *
     * @return object
     */
    public function fetchOrStore(array $data): object
    {
        $this->eventDispatch(
            eventKey: "fetch-or-store.before",
            data: [
                "data" => $data,
            ]
        );

        $created = $this->model->firstOrCreate($data);

        $this->eventDispatch(
            eventKey: "fetch-or-store.after",
            data: $created
        );

        $this->cacheManager->flushAllCache();

        return $created;
    }

    /**
     * store
     *
     * @param  array $data
     *
     * @return object
     */
    public function store(array $data): object
    {
        $this->eventDispatch(
            eventKey: "store.before",
            data: [
                "data" => $data,
            ]
        );
        $created = $this->model->create($data)->fresh();

        $this->eventDispatch(
            eventKey: "store.after",
            data: $created
        );

        $this->cacheManager->flushAllCache();

        return $created;
    }

    /**
     * Saves data with current model instance
     *
     * @param  object $model
     * @param  array $data
     *
     * @return object
     */
    public function save(object $model, array $data): object
    {
        $this->eventDispatch(
            eventKey: "save.before",
            data: [
                $this->tableName => $model,
                "data" => $data,
            ]
        );

        $model->fill($data)->save();

        $this->eventDispatch(
            eventKey: "save.after",
            data: $model
        );

        $this->cacheManager->flushAllCache();

        return $model;
    }

    /**
     * Query database with id and update.
     *
     * @param  array $data
     * @param  string|int $id
     *
     * @return object
     */
    public function update(array $data, string|int $id): object
    {
        $this->eventDispatch(
            eventKey: "update.before",
            data: [
                "id" => $id,
                "data" => $data,
            ]
        );

        $updated = $this->model->whereId($id)->firstOrFail();
        $updated->update($data);

        $this->eventDispatch(
            eventKey: "update.after",
            data: $updated
        );

        $this->cacheManager->flushAllCache();

        return $updated;
    }

    /**
     * Bulk insert data.
     *
     * @param  array $data
     *
     * @return bool
     */
    public function insert(array $data): bool
    {
        $this->eventDispatch(
            eventKey: "insert.before",
            data: [
                "data" => $data,
            ]
        );

        $insert = $this->model->insert($data);

        $this->eventDispatch(
            eventKey: "insert.after",
            data: $data
        );

        $this->cacheManager->flushAllCache();

        return $insert;
    }

    /**
     * Query database with id and delete.
     *
     * @param  int|string $id
     *
     * @return void
     */
    public function delete(int|string $id): void
    {
        $this->eventDispatch(
            eventKey: "delete.before",
            data: [
                "id" => $id,
            ]
        );

        $this->model::whereId($id)->delete();

        $this->eventDispatch(
            eventKey: "delete.after",
            data: [
                "id" => $id,
            ]
        );

        $this->cacheManager->flushAllCache();
    }

    /**
     * Query database with ids and delete. Returns count for deleted ids
     *
     * @param  int|string $ids
     *
     * @return int
     */
    public function bulkDelete(array $ids): int
    {
        $this->eventDispatch(
            eventKey: "bulk-delete.before",
            data: [
                "id" => $ids,
            ]
        );

        $deleteCount = $this->model::whereIn("id", $ids)->delete();

        $this->eventDispatch(
            eventKey: "bulk-delete.after",
            data: [
                "ids" => $ids,
                "delete_count" => $deleteCount,
            ]
        );

        $this->cacheManager->flushAllCache();

        return $deleteCount;
    }

    /**
     * Query database with id and restore.
     *
     * @param  int|string $id
     *
     * @return object
     */
    public function restore(int|string $id): object
    {
        $this->eventDispatch(
            eventKey: "restore.before",
            data: [
                "id" => $id,
            ]
        );

        $deleted = $this->model->onlyTrashed()->findOrFail($id);
        $deleted->restore();

        $this->eventDispatch(
            eventKey: "restore.after",
            data: $deleted
        );

        $this->cacheManager->flushAllCache();

        return $deleted;
    }

    /**
     * Query database with ids and bulk restore. Returns count of restored query.
     *
     * @param  array $id
     *
     * @return int
     */
    public function bulkRestore(array $ids): int
    {
        $this->eventDispatch(
            eventKey: "bulk-restore.before",
            data: [
                "ids" => $ids,
            ]
        );

        $restoreCount = $this->model::withTrashed()
            ->whereIn("id", $ids)
            ->restore();

        $this->eventDispatch(
            eventKey: "bulk-restore.after",
            data: [
                "ids" => $ids,
                "restore_count" => $restoreCount,
            ]
        );

        $this->cacheManager->flushAllCache();

        return $restoreCount;
    }

    /**
     * Sync relations
     *
     * It dose not requires any event
     *
     * @param object $model
     * @param string $relation
     * @param mixed $attributes
     * @param boolean $detaching
     *
     * @return object
     */
    public function sync(
        object $model,
        string $relation,
        mixed $attributes,
        bool $detaching = true
    ): object {
        $model->{$relation}()->sync($attributes, $detaching);
        $this->cacheManager->flushAllCache();
        return $model;
    }

    /**
     * Sync relation without detaching.
     *
     * @param object $model
     * @param string $relation
     * @param array $attributes
     *
     * @return object
     */
    public function syncWithoutDetaching(object $model, string $relation, array $attributes): object
    {
        $this->sync($model, $relation, $attributes, false);
        return $model;
    }

    /**
     * Get object
     *
     * @param  mixed $id
     * @param  mixed $with
     *
     * @return object
     */
    public function get(mixed $id, array $with = [], array $tags = []): mixed
    {
        $this->eventDispatch(
            eventKey: "get-single.before",
            data: [
                "id" => $id,
                "with" => $with,
            ]
        );

        $rows = $this->model::query();
        if ($with != []) {
            $rows = $rows->with($with);
        }
        $fetched = $this->cacheManager->make(
            relates: array_merge($with, $tags),
            callback: function () use ($rows, $id) {
                return $rows->find($id);
            },
            identifier: [$id, $with]
        );

        $this->eventDispatch(
            eventKey: "get-single.after",
            data: $fetched
        );

        return $fetched;
    }

    /**
     * Fetch by specific column
     *
     * @param string $column
     * @param mixed $value
     * @param array $with
     * @param boolean $multiple
     * @return mixed
     */
    public function getBy(
        string $column,
        mixed $value,
        array $with = [],
        bool $multiple = false
    ): mixed {
        $this->eventDispatch(
            eventKey: "get-by-column-single.before",
            data: [
                "column" => $column,
                "value" => $value,
                "with" => $with,
            ]
        );

        $rows = $this->model::query();
        if ($with != []) {
            $rows = $rows->with($with);
        }
        $fetched = $this->cacheManager->make(
            relates: $with,
            callback: function () use ($rows, $column, $value, $multiple) {
                if (is_array($value)) {
                    $rows->whereIn($column, $value);
                } else {
                    $rows->where($column, $value);
                }
                if ($multiple) {
                    return $rows->get();
                } else {
                    return $rows->first();
                }
            },
            identifier: [$column, $value, $with, $multiple]
        );

        $this->eventDispatch(
            eventKey: "get-by-column-single.after",
            data: $fetched
        );

        return $fetched;
    }

    /**
     * Query database with condition and update multiple rows.
     *
     * @param array $conditions
     * @param array $data
     * @return int
     */
    public function bulkUpdate(array $conditions, array $data): int
    {
        $this->eventDispatch(
            eventKey: "bulk.update.before",
            data: [
                "conditions" => $conditions,
                "data" => $data,
            ]
        );

        $updated = $this->model->where($conditions)->update($data);

        $this->eventDispatch(
            eventKey: "bulk.update.after",
            data: $updated
        );

        $this->cacheManager->flushAllCache();

        return $updated;
    }
}
