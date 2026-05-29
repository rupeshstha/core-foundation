<?php

namespace CoreFoundation\Tests\Unit\Traits;

use RuntimeException;
use CoreFoundation\Traits\HasEvent;
use Illuminate\Support\Facades\Event;
use CoreFoundation\Tests\PackageTestCase;

class PrefixedEventService
{
    use HasEvent;

    public function __construct()
    {
        $this->eventPrefix = 'order';
    }

    public function fire(string $event, mixed $payload = []): void
    {
        $this->dispatch($event, $payload);
    }

    public function fireRaw(string $event, mixed $payload = []): void
    {
        $this->dispatch($event, $payload, prefixed: false);
    }

    public function runWithoutEvents(callable $callback): mixed
    {
        return $this->withoutEvents($callback);
    }
}

class HasEventExtendedTest extends PackageTestCase
{
    public function test_it_bypasses_prefix_when_prefixed_false(): void
    {
        Event::fake();

        $service = new PrefixedEventService;
        $service->fireRaw('global.event', ['data' => 1]);

        Event::assertDispatched('global.event');
        Event::assertNotDispatched('order.global.event');
    }

    public function test_without_events_returns_callback_result(): void
    {
        $service = new PrefixedEventService;
        $result = $service->runWithoutEvents(fn () => 'the result');

        $this->assertEquals('the result', $result);
    }

    public function test_without_events_restores_dispatch_after_exception(): void
    {
        Event::fake();

        $service = new PrefixedEventService;

        try {
            $service->runWithoutEvents(function () {
                throw new RuntimeException('inside callback');
            });
        } catch (RuntimeException) {
            // expected
        }

        // Events should be restored — fire one and check it dispatches
        $service->fire('placed');
        Event::assertDispatched('order.placed');
    }

    public function test_events_disabled_inside_without_events(): void
    {
        Event::fake();

        $service = new PrefixedEventService;
        $service->runWithoutEvents(function () use ($service) {
            $service->fire('should-not-fire');
        });

        Event::assertNotDispatched('order.should-not-fire');
    }

    public function test_events_re_enabled_after_without_events(): void
    {
        Event::fake();

        $service = new PrefixedEventService;
        $service->runWithoutEvents(fn () => null);

        $service->fire('after-restore');
        Event::assertDispatched('order.after-restore');
    }

    public function test_dispatch_without_prefix_when_event_prefix_is_null(): void
    {
        Event::fake();

        $service = new class {
            use HasEvent;

            public function fire(string $event): void
            {
                $this->dispatch($event);
            }
        };

        $service->fire('no-prefix-event');
        Event::assertDispatched('no-prefix-event');
    }
}
