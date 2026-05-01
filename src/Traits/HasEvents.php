<?php

namespace CoreFoundation\Traits;

use Illuminate\Support\Facades\Event;

trait HasEvents
{
    protected ?string $eventPrefix = null;

    protected bool $eventDispatch = true;

    public function eventDispatch(string $eventKey, mixed $data = [], bool $restrictEventPrefix = false): void
    {
        if ($this->eventPrefix && ! $restrictEventPrefix) {
            $eventKey = "{$this->eventPrefix}.{$eventKey}";
        }

        if ($this->eventDispatch) {
            Event::dispatch($eventKey, $data);
        }
    }
}
