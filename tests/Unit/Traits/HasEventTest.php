<?php

namespace CoreFoundation\Tests\Unit\Traits;

use CoreFoundation\Traits\HasEvent;
use Illuminate\Support\Facades\Event;
use CoreFoundation\Tests\PackageTestCase;

class TestEventService
{
    use HasEvent;

    public function __construct()
    {
        $this->eventPrefix = 'test';
    }

    public function fire($event, $payload = [])
    {
        $this->dispatch($event, $payload);
    }
}

class HasEventTest extends PackageTestCase
{
    public function test_it_dispatches_prefixed_events(): void
    {
        Event::fake();

        $service = new TestEventService;
        $service->fire('happened', ['foo' => 'bar']);

        Event::assertDispatched('test.happened', function ($event, $payload) {
            return $payload === ['foo' => 'bar'];
        });
    }

    public function test_it_can_suppress_events(): void
    {
        Event::fake();

        $service = new TestEventService;
        $service->withoutEvents(function () use ($service) {
            $service->fire('happened');
        });

        Event::assertNotDispatched('test.happened');
    }
}
