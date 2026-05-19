<?php

namespace CoreFoundation\Traits\Models;

/**
 * ModelFillables
 *
 * Allows external modules to add fillable fields to a model without
 * modifying the model's source — the core modular extensibility problem.
 *
 * Register from a module's ServiceProvider::boot():
 *
 *   Order::addFillable(['subscription_id', 'plan_code']);
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHY THIS EXISTS                                                             │
 * │                                                                             │
 * │ In a modular monolith, Module B (Subscriptions) may need to add fields to  │
 * │ Module A's (Orders) model. Without this pattern, Module B must edit         │
 * │ Module A's source — breaking module isolation.                              │
 * │                                                                             │
 * │ With this pattern, Module B registers its fields in its own ServiceProvider │
 * │ and Module A's model is never touched.                                      │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait ModelFillables
{
    /**
     * Registry of additional fillable fields keyed by model class.
     * Keyed by static::class to prevent subclass collision.
     *
     * @var array<class-string, array<string>>
     */
    protected static array $additionalFillable = [];

    /**
     * Add fillable fields to this model from an external module.
     * Call from ServiceProvider::boot() — never from the model itself.
     *
     * @param  array<string>  $fillable  Index array of column names
     */
    public static function addFillable(array $fillable): void
    {
        static::$additionalFillable[static::class] = array_unique(array_merge(
            static::$additionalFillable[static::class] ?? [],
            $fillable,
        ));
    }

    /**
     * Returns additional fillable fields registered for this model.
     *
     * @return array<string>
     */
    public static function getAdditionalFillable(): array
    {
        return static::$additionalFillable[static::class] ?? [];
    }

    /**
     * Override Eloquent's getFillable() to merge additional fillable fields.
     *
     * @return array<string>
     */
    public function getFillable(): array
    {
        return array_values(array_unique(
            array_merge($this->fillable, static::getAdditionalFillable())
        ));
    }
}
