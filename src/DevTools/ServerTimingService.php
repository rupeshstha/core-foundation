<?php

namespace CoreFoundation\DevTools\ServerTiming;

use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\Stopwatch\StopwatchEvent;

/**
 * ServerTimingService
 *
 * Tracks named performance measurements and exposes them as
 * W3C Server-Timing header values.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ OCTANE SAFETY                                                               │
 * │                                                                             │
 * │ This service is bound as a SCOPED singleton via the ServiceProvider.        │
 * │ Scoped bindings are re-created on every Octane request, so state from       │
 * │ one request never leaks into the next.                                      │
 * │                                                                             │
 * │ If you manually bind this service outside the ServiceProvider, ensure       │
 * │ you either use scoped() or listen to RequestReceived and call reset().      │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE                                                                       │
 * │                                                                             │
 * │   use CoreFoundation\Facades\ServerTiming;                                  │
 * │                                                                             │
 * │   // Start/stop a measurement                                               │
 * │   ServerTiming::start('db-query');                                          │
 * │   $users = User::all();                                                     │
 * │   ServerTiming::stop('db-query');                                           │
 * │                                                                             │
 * │   // Toggle: start on first call, stop on second                            │
 * │   ServerTiming::measure('db-query');  // starts                             │
 * │   ServerTiming::measure('db-query');  // stops                              │
 * │                                                                             │
 * │   // Known duration (milliseconds)                                          │
 * │   ServerTiming::record('cache-hit', 0.4);                                   │
 * │                                                                             │
 * │   // Callable — measured automatically                                      │
 * │   ServerTiming::wrap('transform', fn () => $this->transform($users));       │
 * │                                                                             │
 * │   // Text-only metric (no duration)                                         │
 * │   ServerTiming::label('user', auth()->id());                                │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
final class ServerTimingService
{
    /**
     * Completed measurements: [ name => float|null ]
     * null = text-only metric, float = duration in ms
     */
    private array $completed = [];

    /**
     * Descriptions for metrics: [ name => string ]
     * Shown as desc= in the Server-Timing header.
     */
    private array $descriptions = [];

    /**
     * Tracks which events have been started but not yet stopped.
     */
    private array $started = [];

    public function __construct(
        private readonly Stopwatch $stopwatch,
    ) {}

    // =========================================================================
    // Core measurement API
    // =========================================================================

    /**
     * Start a named measurement.
     * Safe to call multiple times — subsequent calls are ignored if already started.
     *
     * @param  string       $name      Measurement name, used as the metric id
     * @param  string|null  $description  Human-readable description shown in DevTools
     */
    public function start(string $name, ?string $description = null): self
    {
        if (isset($this->started[$name])) {
            return $this;
        }

        $this->stopwatch->start($name);
        $this->started[$name] = true;

        if ($description !== null) {
            $this->descriptions[$name] = $description;
        }

        return $this;
    }

    /**
     * Stop a named measurement and record its duration.
     * Safe to call even if the measurement was never started — silently ignored.
     */
    public function stop(string $name): self
    {
        if (! $this->stopwatch->isStarted($name)) {
            return $this;
        }

        $event = $this->stopwatch->stop($name);
        $this->completed[$name] = $event->getDuration();

        unset($this->started[$name]);

        return $this;
    }

    /**
     * Toggle: starts the measurement on the first call, stops it on the second.
     * Useful for wrapping a block without needing start/stop pairs.
     */
    public function measure(string $name, ?string $description = null): self
    {
        return isset($this->started[$name])
            ? $this->stop($name)
            : $this->start($name, $description);
    }

    /**
     * Record a known duration in milliseconds without using the stopwatch.
     * Use when you have an external timing source (e.g. a database query log).
     *
     * @param  float  $durationMs  Duration in milliseconds
     */
    public function record(string $name, float $durationMs, ?string $description = null): self
    {
        $this->completed[$name] = $durationMs;

        if ($description !== null) {
            $this->descriptions[$name] = $description;
        }

        return $this;
    }

    /**
     * Measure a callable and record its duration.
     * The callable's return value is passed through unchanged.
     *
     * @template T
     * @param  callable(): T  $callable
     * @return T
     */
    public function wrap(string $name, callable $callable, ?string $description = null): mixed
    {
        $this->start($name, $description);

        try {
            $result = $callable();
        } finally {
            $this->stop($name);
        }

        return $result;
    }

    /**
     * Add a text-only metric with no duration.
     * Appears as a label in DevTools: "name: value"
     */
    public function label(string $name, string|int|float $value): self
    {
        $this->completed["{$name}:{$value}"] = null;

        return $this;
    }

    // =========================================================================
    // Lifecycle
    // =========================================================================

    /**
     * Stop all measurements that were started but never explicitly stopped.
     * Called by the middleware before building the response header.
     */
    public function flush(): void
    {
        foreach (array_keys($this->started) as $name) {
            $this->stop($name);
        }
    }

    /**
     * Reset all state — called between Octane requests.
     * Also useful in tests to isolate measurements between test cases.
     */
    public function reset(): void
    {
        // Re-create the Stopwatch to fully clear its internal state.
        // Calling reset() on an existing Stopwatch does not clear already-stopped events.
        $this->completed    = [];
        $this->descriptions = [];
        $this->started      = [];

        // Stop any Stopwatch events that are still running to prevent
        // "Event not started" exceptions on the new Stopwatch instance.
        foreach (array_keys($this->started) as $name) {
            if ($this->stopwatch->isStarted($name)) {
                $this->stopwatch->stop($name);
            }
        }
    }

    // =========================================================================
    // Header generation
    // =========================================================================

    /**
     * Build the W3C Server-Timing header value from all completed measurements.
     *
     * Format per spec: name;desc="description";dur=123.4, name2;dur=456.7
     * https://www.w3.org/TR/server-timing/
     */
    public function toHeaderValue(): string
    {
        $parts = [];

        foreach ($this->completed as $name => $duration) {
            $id   = $this->toMetricId($name);
            $desc = $this->descriptions[$name] ?? $name;

            $part = "{$id};desc=\"{$desc}\"";

            if ($duration !== null) {
                $part .= ";dur={$duration}";
            }

            $parts[] = $part;
        }

        return implode(', ', $parts);
    }

    /**
     * Whether there are any measurements to report.
     */
    public function isEmpty(): bool
    {
        return empty($this->completed);
    }

    /**
     * All completed measurements as an associative array.
     * Useful for logging, testing, and custom header building.
     *
     * @return array<string, float|null>
     */
    public function all(): array
    {
        return $this->completed;
    }

    // =========================================================================
    // Internal
    // =========================================================================

    /**
     * Convert a measurement name to a valid Server-Timing metric identifier.
     * Metric IDs must be tokens per RFC 7230 — no spaces, quotes, or special chars.
     */
    private function toMetricId(string $name): string
    {
        return preg_replace('/[^a-zA-Z0-9\-_.]/', '-', $name);
    }
}
