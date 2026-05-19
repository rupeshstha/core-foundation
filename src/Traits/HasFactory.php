<?php

namespace CoreFoundation\Traits;

use Closure;
use LogicException;

/**
 * HasFactory
 *
 * Conditional service preference system — inspired by Magento's preference/plugin
 * model, built on top of Laravel's service container.
 *
 * Allows a concrete service class to be swapped for another at runtime based on
 * a condition (feature flag, config value, tenant context, etc.) without the
 * caller knowing or caring which implementation they receive.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ IMPORTANT: This is a thin convention layer over Laravel's container.        │
 * │ For most cases, Laravel's native container binding is simpler:              │
 * │                                                                             │
 * │   $this->app->bind(OrderService::class, fn () =>                            │
 * │       config('features.subscriptions')                                      │
 * │           ? app(SubscriptionOrderService::class)                            │
 * │           : app(OrderService::class)                                        │
 * │   );                                                                        │
 * │                                                                             │
 * │ Use HasFactory when the swap condition needs to be declared by the module   │
 * │ that owns the override (e.g. a plugin package), not by the host app.        │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ SETUP (in a module's ServiceProvider::register())                           │
 * │                                                                             │
 * │   // A subscription module registers its override:                          │
 * │   OrderService::setPreference(                                              │
 * │       concrete:   SubscriptionOrderService::class,                          │
 * │       condition:  fn () => config('modules.subscriptions.enabled'),         │
 * │   );                                                                        │
 * │                                                                             │
 * │   // Bind through the container so all resolution goes through factory():   │
 * │   $this->app->bind(OrderService::class, fn () =>                            │
 * │       (new OrderService)->make()                                            │
 * │   );                                                                        │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ RULES                                                                       │
 * │                                                                             │
 * │  - One preference per service class (single override model)                 │
 * │  - The concrete MUST extend the service it replaces                         │
 * │  - Conditions must be cheap — config/feature-flag reads only, no DB calls   │
 * │  - Register in ServiceProvider::register(), not boot()                      │
 * │  - Use clearPreference() in tests to reset between cases                    │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait HasFactory
{
    /**
     * Preference registry keyed by static::class — one slot per service class.
     * Keyed by static::class (not self::class) to prevent subclass collision.
     */
    private static array $preferences = [];

    // =========================================================================
    // Registration
    // =========================================================================

    /**
     * Register a conditional preference for this service class.
     *
     * @param  string  $concrete  FQCN of the replacement. Must extend this service.
     * @param  Closure|string  $condition  Evaluated at resolution time.
     *                                     Closure: must return bool.
     *                                     String:  resolved from container, must have handle(): bool.
     */
    public static function setPreference(string $concrete, Closure|string $condition): void
    {
        static::$preferences[static::class] = [
            'concrete' => $concrete,
            'condition' => $condition,
        ];
    }

    /**
     * Clear the registered preference for this service class.
     * Call in test setUp/tearDown to reset between cases.
     */
    public static function clearPreference(): void
    {
        unset(static::$preferences[static::class]);
    }

    // =========================================================================
    // Resolution
    // =========================================================================

    /**
     * Resolve to the registered concrete if the condition passes,
     * otherwise return $this (the original instance).
     *
     * Recommended usage — bind via the container:
     *
     *   $this->app->bind(OrderService::class, fn () =>
     *       (new OrderService)->resolvePreference()
     *   );
     *
     * Or call directly when you need the right concrete at a specific point:
     *
     *   $service = (new OrderService)->resolvePreference();
     */
    public function resolvePreference(): static
    {
        $preference = static::$preferences[static::class] ?? null;

        if ($preference === null) {
            return $this;
        }

        $condition = $preference['condition'];

        $passes = $condition instanceof Closure
            ? $condition()
            : resolve($condition)->handle();

        if (! $passes) {
            return $this;
        }

        $concrete = $preference['concrete'];

        if (! is_a($concrete, static::class, true)) {
            throw new LogicException(
                "Preference [{$concrete}] must extend [".static::class.']. '.
                'A preference must be a subclass of the service it replaces.'
            );
        }

        return resolve($concrete);
    }
}
