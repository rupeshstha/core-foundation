<?php

namespace CoreFoundation\Broadcasting\Abstracts;

use Illuminate\Database\Eloquent\Model;
use CoreFoundation\Broadcasting\Contracts\SocketPayloadTransformer;

/**
 * ModelBroadcastEvent
 *
 * Specific base for broadcasting Eloquent model changes.
 */
abstract class ModelBroadcastEvent extends ScopedBroadcastEvent
{
    public function __construct(
        protected Model $model,
        protected string $action,
        protected ?SocketPayloadTransformer $transformer = null
    ) {}

    protected function getPayload(): array
    {
        return [
            'action' => $this->action,
            'model' => $this->model->getTable(),
            'id' => $this->model->getKey(),
            'data' => $this->transformer
                ? $this->transformer->transform($this->model)
                : $this->model->toArray(),
        ];
    }
}
