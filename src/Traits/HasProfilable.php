<?php

namespace CoreFoundation\Traits;

use ReflectionMethod;
use CoreFoundation\Attributes\Profile;
use CoreFoundation\Facades\ServerTiming;
use CoreFoundation\Services\BaseService;
use CoreFoundation\Repositories\BaseRepository;

/**
 * HasProfilable
 *
 * Provides automatic method profiling via the #[Profile] attribute.
 * When a method marked #[Profile] is called through profile(), it is
 * wrapped in a Server-Timing measurement automatically.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE — Opt-in per service or repository                                    │
 * │                                                                             │
 * │   class OrderService extends BaseService                                    │
 * │   {                                                                         │
 * │       use HasProfilable;                                                    │
 * │                                                                             │
 * │       #[Profile]                                                            │
 * │       public function place(array $validated): PlaceOrderData               │
 * │       {                                                                     │
 * │           // Method body — profiling is applied by the caller               │
 * │       }                                                                     │
 * │   }                                                                         │
 * │                                                                             │
 * │ Calling convention — use $this->profile() to invoke profiled methods:       │
 * │                                                                             │
 * │   // In a controller or parent service:                                     │
 * │   $result = $this->orderService->profile('place', [$validated]);            │
 * │                                                                             │
 * │ Or let ProfilingMiddleware handle it automatically via Reflection            │
 * │ for registered service classes.                                             │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ HOW IT WORKS                                                                │
 * │                                                                             │
 * │ profile() checks whether the target method has #[Profile] attribute.        │
 * │ If yes AND profiling is enabled for this layer → wraps in ServerTiming.     │
 * │ If no → calls the method directly. Zero overhead on non-profiled methods.  │
 * │                                                                             │
 * │ Attribute metadata is resolved via Reflection and cached statically so      │
 * │ repeat calls to the same method don't re-read attributes.                  │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait HasProfilable
{
    /**
     * Reflection cache — method attribute metadata keyed by "Class::method".
     * Static so it's resolved once per process, not once per call.
     *
     * @var array<string, Profile|null>
     */
    private static array $profileAttributeCache = [];

    /**
     * Invoke a method on this class with optional profiling.
     *
     * If the method has #[Profile] and profiling is enabled for this layer,
     * the execution is wrapped in a Server-Timing measurement.
     * Otherwise the method is called directly — zero overhead.
     *
     * @param  string  $method  The method name to call
     * @param  array  $args  Arguments to pass
     */
    final public function profile(string $method, array $args = []): mixed
    {
        $attribute = $this->resolveProfileAttribute($method);

        if ($attribute === null || ! $this->isProfilingEnabled()) {
            return $this->$method(...$args);
        }

        // Resolve metric label and description from attribute or defaults
        $label = $attribute->label ?? class_basename(static::class).'.'.$method;
        $description = $attribute->description ?? static::class.'::'.$method;

        $result = ServerTiming::wrap($label, fn () => $this->$method(...$args), $description);

        // Slow method warning — adds "slow-{label}" metric to Server-Timing
        $this->maybeRecordSlowWarning($label, $description);

        return $result;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Read the #[Profile] attribute from the given method, with caching.
     */
    private function resolveProfileAttribute(string $method): ?Profile
    {
        $cacheKey = static::class.'::'.$method;

        if (array_key_exists($cacheKey, static::$profileAttributeCache)) {
            return static::$profileAttributeCache[$cacheKey];
        }

        if (! method_exists($this, $method)) {
            return static::$profileAttributeCache[$cacheKey] = null;
        }

        $reflection = new ReflectionMethod($this, $method);
        $attributes = $reflection->getAttributes(Profile::class);

        $instance = empty($attributes)
            ? null
            : $attributes[0]->newInstance();

        return static::$profileAttributeCache[$cacheKey] = $instance;
    }

    /**
     * Whether profiling is currently active for this class's layer.
     * Reads config/profiling.php enabled flag and environment check.
     */
    private function isProfilingEnabled(): bool
    {
        if (! config('profiling.enabled', false)) {
            return false;
        }

        $environments = config('profiling.environments', []);

        if (! empty($environments) && ! app()->environment($environments)) {
            return false;
        }

        // Determine layer from class hierarchy
        $layer = match (true) {
            is_a($this, BaseService::class) => 'services',
            is_a($this, BaseRepository::class) => 'repositories',
            default => 'services',
        };

        return (bool) config("profiling.layers.{$layer}", true);
    }

    /**
     * Record a "slow method" warning metric if the last measurement exceeded threshold.
     * Reads the last recorded Server-Timing duration and compares to config threshold.
     */
    private function maybeRecordSlowWarning(string $label, string $description): void
    {
        $layer = match (true) {
            is_a($this, BaseService::class) => 'service',
            is_a($this, BaseRepository::class) => 'repository',
            default => 'service',
        };

        $threshold = config("profiling.thresholds.{$layer}");

        if ($threshold === null) {
            return;
        }

        $duration = ServerTiming::all()[$label] ?? null;

        if ($duration !== null && $duration >= $threshold) {
            ServerTiming::record(
                name: "slow-{$label}",
                durationMs: $duration,
                description: "Slow: {$description} exceeded {$threshold}ms threshold",
            );
        }
    }
}
