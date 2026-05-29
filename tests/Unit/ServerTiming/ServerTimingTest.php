<?php

namespace CoreFoundation\Tests\Unit\ServerTiming;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\Stopwatch;
use CoreFoundation\DevTools\ServerTiming\ServerTimingService;

class ServerTimingTest extends TestCase
{
    public function test_it_can_set_custom_measures(): void
    {
        $timing = new ServerTimingService(new Stopwatch);
        $timing->record('key', 1000);

        $events = $timing->all();

        $this->assertCount(1, $events);
        $this->assertTrue(array_key_exists('key', $events));
        $this->assertSame(1000.0, $events['key']);
    }

    public function test_it_can_start_and_stop_events(): void
    {
        $timing = new ServerTimingService(new Stopwatch);
        $timing->start('key');
        usleep(10000); // 10ms
        $timing->stop('key');

        $events = $timing->all();

        $this->assertCount(1, $events);
        $this->assertTrue(array_key_exists('key', $events));
        $this->assertGreaterThanOrEqual(10, $events['key']);
    }

    public function test_it_can_start_and_stop_events_using_measure(): void
    {
        $timing = new ServerTimingService(new Stopwatch);
        $timing->measure('key');
        usleep(10000);
        $timing->measure('key');

        $events = $timing->all();

        $this->assertCount(1, $events);
        $this->assertTrue(array_key_exists('key', $events));
        $this->assertGreaterThanOrEqual(10, $events['key']);
    }

    public function test_it_can_set_multiple_events(): void
    {
        $timing = new ServerTimingService(new Stopwatch);
        $timing->record('key_1', 1000);
        $timing->record('key_2', 2000);

        $events = $timing->all();

        $this->assertCount(2, $events);
        $this->assertTrue(array_key_exists('key_1', $events));
        $this->assertTrue(array_key_exists('key_2', $events));

        $this->assertSame(1000.0, $events['key_1']);
        $this->assertSame(2000.0, $events['key_2']);
    }

    public function test_it_can_set_events_without_duration(): void
    {
        $timing = new ServerTimingService(new Stopwatch);
        $timing->label('user', '1');

        $events = $timing->all();

        $this->assertCount(1, $events);
        $this->assertTrue(array_key_exists('user:1', $events));
        $this->assertNull($events['user:1']);
    }

    public function test_it_can_stop_started_events_on_flush(): void
    {
        $timing = new ServerTimingService(new Stopwatch);
        $timing->start('Started');

        $timing->flush();
        $events = $timing->all();

        $this->assertCount(1, $events);
        $this->assertTrue(array_key_exists('Started', $events));
        $this->assertNotNull($events['Started']);
    }

    public function test_it_can_set_durations_with_wrap(): void
    {
        $timing = new ServerTimingService(new Stopwatch);
        $timing->wrap('callable', function () {
            usleep(10000);
        });

        $events = $timing->all();

        $this->assertCount(1, $events);
        $this->assertTrue(array_key_exists('callable', $events));
        $this->assertTrue($events['callable'] >= 10);
    }
}
