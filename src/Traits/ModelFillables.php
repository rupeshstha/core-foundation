<?php

namespace CoreFoundation\Traits;

trait ModelFillables
{
    protected static $additionalFillable = [];

    public function getFillable(): array
    {
        return array_values(array_unique(array_merge($this->fillable, static::getAdditionalFillable())));
    }

    /**
     * This method will add model fillable. Fillable array should always be in index array.
     *
     * @param array $fillable
     *
     * @return void
     *
     */
    public static function addFillable(array $fillable = []): void
    {
        if (isset(static::$additionalFillable[static::class])) {
            static::$additionalFillable[static::class] = array_merge(static::$additionalFillable[static::class], $fillable);
        } else {
            static::$additionalFillable[static::class] = $fillable;
        }
    }

    /**
     * Get all the additionally bind fillable.
     *
     * @return array
     *
     */
    public static function getAdditionalFillable(): array
    {
        return isset(static::$additionalFillable[static::class])
            ? static::$additionalFillable[static::class]
            : [];
    }
}
