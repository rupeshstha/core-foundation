<?php

namespace CoreFoundation\Traits;

trait Searchable
{
    protected static array $searchable = [];

    public static function getSearchable(): array
    {
        return array_values(array_unique(array_merge(static::searchable(), static::getAdditionalSearchable())));
    }

    /**
     * Add searchable table columns.
     *
     * @return array
     */
    public static function searchable(): array
    {
        return [];
    }

    public static function addSearchable(array $additionalSearchable): void
    {
        if (isset(static::$searchable[static::class])) {
            static::$searchable[static::class] = array_merge(static::$searchable[static::class], $additionalSearchable);
        } else {
            static::$searchable[static::class] = $additionalSearchable;
        }
    }

    /**
     * Get all the additionally bind searchable.
     *
     * @return array
     *
     */
    public static function getAdditionalSearchable(): array
    {
        return isset(static::$searchable[static::class])
            ? static::$searchable[static::class]
            : [];
    }
}
