<?php

namespace CoreFoundation\Observers;

use CoreFoundation\Broadcasting\Abstracts\TenantBroadcastEvent;
use Illuminate\Database\Eloquent\Model;

/**
 * BroadcastObserver
 *
 * Automatically broadcasts model changes to tenant channels.
 *
 * USAGE:
 *   1. Create an event extending TenantBroadcastEvent
 *   2. Register this observer for your model:
 *      MyModel::observe(new BroadcastObserver(MyModelChanged::class));
 */
class BroadcastObserver
{
    /**
     * @param class-string<TenantBroadcastEvent> $eventClass
     */
    public function __construct(
        protected string $eventClass
    ) {}

    public function created(Model $model): void
    {
        $this->broadcast($model, 'created');
    }

    public function updated(Model $model): void
    {
        $this->broadcast($model, 'updated');
    }

    public function deleted(Model $model): void
    {
        $this->broadcast($model, 'deleted');
    }

    protected function broadcast(Model $model, string $action): void
    {
        event(new $this->eventClass($model, $action));
    }
}
