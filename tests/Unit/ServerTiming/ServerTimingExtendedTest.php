<?php

namespace CoreFoundation\Tests\Unit\ServerTiming;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Stopwatch\Stopwatch;
use CoreFoundation\DevTools\ServerTiming\ServerTimingService;

class ServerTimingExtendedTest extends TestCase
{
    private function make(): ServerTimingService
    {
        return new ServerTimingService(new Stopwatch);
    }

    public function test_is_empty_when_no_measurements(): void
    {
        $timing = $this->make();
        $this->assertTrue($timing->isEmpty());
    }

    public function test_is_not_empty_after_record(): void
    {
        $timing = $this->make();
        $timing->record('op', 10.0);

        $this->assertFalse($timing->isEmpty());
    }

    public function test_all_returns_completed_measurements(): void
    {
        $timing = $this->make();
        $timing->record('a', 10.0);
        $timing->record('b', 20.0);

        $all = $timing->all();

        $this->assertCount(2, $all);
        $this->assertEquals(10.0, $all['a']);
        $this->assertEquals(20.0, $all['b']);
    }

    public function test_reset_clears_all_state(): void
    {
        $timing = $this->make();
        $timing->record('metric', 100.0);
        $timing->start('running');

        $timing->reset();

        $this->assertTrue($timing->isEmpty());
        $this->assertEquals([], $timing->all());
    }

    public function test_to_header_value_builds_valid_server_timing_string(): void
    {
        $timing = $this->make();
        $timing->record('db', 12.5);

        $header = $timing->toHeaderValue();

        $this->assertStringContainsString('db', $header);
        $this->assertStringContainsString('dur=12.5', $header);
    }

    public function test_to_header_value_includes_description(): void
    {
        $timing = $this->make();
        $timing->record('cache', 3.0, 'Cache read');

        $header = $timing->toHeaderValue();

        $this->assertStringContainsString('desc="Cache read"', $header);
    }

    public function test_to_header_value_omits_dur_for_label_metrics(): void
    {
        $timing = $this->make();
        $timing->label('user', 42);

        $header = $timing->toHeaderValue();

        $this->assertStringNotContainsString('dur=', $header);
        $this->assertStringContainsString('user', $header);
    }

    public function test_to_header_value_separates_multiple_metrics_with_comma(): void
    {
        $timing = $this->make();
        $timing->record('a', 1.0);
        $timing->record('b', 2.0);

        $header = $timing->toHeaderValue();
        $parts = explode(', ', $header);

        $this->assertCount(2, $parts);
    }

    public function test_repeated_start_is_ignored(): void
    {
        $timing = $this->make();
        $timing->start('op');
        $timing->start('op'); // second call ignored

        $timing->stop('op');

        $this->assertCount(1, $timing->all());
    }

    public function test_stop_for_non_started_event_is_silently_ignored(): void
    {
        $timing = $this->make();
        $timing->stop('never-started'); // should not throw

        $this->assertTrue($timing->isEmpty());
    }

    public function test_wrap_returns_callable_result(): void
    {
        $timing = $this->make();
        $result = $timing->wrap('op', fn () => 'the-value');

        $this->assertEquals('the-value', $result);
    }

    public function test_record_with_description_stores_description(): void
    {
        $timing = $this->make();
        $timing->record('query', 5.0, 'users count');

        $header = $timing->toHeaderValue();
        $this->assertStringContainsString('desc="users count"', $header);
        $this->assertStringContainsString('dur=5', $header);
    }

    public function test_measure_starts_on_first_call_and_stops_on_second(): void
    {
        $timing = $this->make();
        $this->assertTrue($timing->isEmpty()); // not started yet

        $timing->measure('toggle');
        $this->assertTrue($timing->isEmpty()); // started, not completed

        $timing->measure('toggle');
        $this->assertFalse($timing->isEmpty()); // now completed
    }
}
