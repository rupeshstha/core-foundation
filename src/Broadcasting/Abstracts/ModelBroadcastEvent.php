<?php

namespace CoreFoundation\Broadcasting\Abstracts;

use CoreFoundation\Broadcasting\Contracts\SocketPayloadTransformer;
use Illuminate\Database\Eloquent\Model;

/**
 * ModelBroadcastEvent
 *
 * Specific base for broadcasting Eloquent model changes.
 */
abstract class ModelBroadcastEvent extends TenantBroadcastEvent
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

    protected function getTenantId(): int|string|null
    {
        return $this->model->getAttribute('tenant_id');
    }
}
