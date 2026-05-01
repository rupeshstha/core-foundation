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
     */
    public static function getAdditionalSearchable(): array
    {
        return isset(static::$searchable[static::class])
            ? static::$searchable[static::class]
            : [];
    }

    /**
     * Get the name of the index associated with the model.
     */
    public function searchableAs(): string
    {
        return $this->getTable();
    }

    public function searchableUsing()
    {
        $engines = config('core_foundation.search.engine_map', []);

        if (isset($engines[self::class])) {
            return app(EngineManager::class)->engine(
                $engines[self::class]
            );
        }

        return app(EngineManager::class)->engine();
    }
}
