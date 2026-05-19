<?php

namespace CoreFoundation\Traits\Models;

/**
 * ModelCastables
 *
 * Allows external modules to add Eloquent casts to a model without
 * modifying the model's source.
 *
 * Register from a module's ServiceProvider::boot():
 *
 *   Order::addCast([
 *       'subscription_id' => 'integer',
 *       'metadata'        => 'array',
 *       'activated_at'    => 'datetime',
 *   ]);
 *
 * Supports all Eloquent cast types including custom cast classes:
 *
 *   Order::addCast(['settings' => AsCollection::class]);
 *   Order::addCast(['status'   => OrderStatusEnum::class]);
 */
trait ModelCastables
{
    /**
     * Registry of additional casts keyed by model class.
     *
     * @var array<class-string, array<string, string>>
     */
    protected static array $additionalCasts = [];

    /**
     * Add casts to this model from an external module.
     * Call from ServiceProvider::boot() — never from the model itself.
     *
     * @param  array<string, string>  $casts  Associative array of column => cast type
     */
    public static function addCast(array $casts): void
    {
        static::$additionalCasts[static::class] = array_merge(
            static::$additionalCasts[static::class] ?? [],
            $casts,
        );
    }

    /**
     * Returns additional casts registered for this model.
     *
     * @return array<string, string>
     */
    public static function getAdditionalCasts(): array
    {
        return static::$additionalCasts[static::class] ?? [];
    }

    /**
     * Override Eloquent's getCasts() to merge additional casts.
     * Additional casts take precedence over the model's own casts,
     * allowing modules to override a base cast if needed.
     *
     * @return array<string, string>
     */
    public function getCasts(): array
    {
        return array_merge(parent::getCasts(), static::getAdditionalCasts());
    }
}
