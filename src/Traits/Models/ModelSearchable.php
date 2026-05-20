<?php

namespace CoreFoundation\Traits\Models;

/**
 * ModelSearchable
 *
 * Modular searchable column declaration for BaseModel.
 * Follows the same addFillable() pattern — modules extend searchable
 * columns without touching the model source.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ DECLARING SEARCHABLE COLUMNS                                                │
 * │                                                                             │
 * │ On the model (base declaration):                                            │
 * │                                                                             │
 * │   class Order extends BaseModel                                             │
 * │   {                                                                         │
 * │       use ModelSearchable;  // included via BaseModel already               │
 * │                                                                             │
 * │       protected static array $searchable = [                                │
 * │           'status',                                                         │
 * │           'created_at',                                                     │
 * │       ];                                                                    │
 * │   }                                                                         │
 * │                                                                             │
 * │ From a module ServiceProvider::boot() (modular extension):                  │
 * │                                                                             │
 * │   Order::addSearchable(['subscription_id', 'plan_code']);                   │
 * │                                                                             │
 * │ The repository merges both:                                                 │
 * │                                                                             │
 * │   Order::getSearchable();                                                   │
 * │   // ['status', 'created_at', 'subscription_id', 'plan_code']               │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHY NOT 'searchable'?                                                       │
 * │                                                                             │
 * │ Laravel Scout uses $searchable and searchableAs(). This trait uses          │
 * │ $searchable as a static array property and getSearchable() as the accessor  │
 * │ to avoid collision with Scout's instance method searchableAs().             │
 * │ If you use Scout, declare your columns in $searchable on the model          │
 * │ and Scout will not interfere — they serve different purposes.               │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait ModelSearchable
{
    /**
     * Columns allowed for filtering on this model.
     * Override in each model to declare the base filterable surface.
     *
     * @var array<string>
     */
    protected static array $searchable = [];

    /**
     * Additional searchable columns registered by modules.
     * Keyed by static::class to prevent subclass collision.
     *
     * @var array<class-string, array<string>>
     */
    protected static array $additionalSearchable = [];

    /**
     * Add searchable columns from an external module.
     * Call from ServiceProvider::boot() — never from the model itself.
     *
     * @param  array<string>  $columns
     */
    public static function addSearchable(array $columns): void
    {
        static::$additionalSearchable[static::class] = array_unique(array_merge(
            static::$additionalSearchable[static::class] ?? [],
            $columns,
        ));
    }

    /**
     * Get all searchable columns — model declaration merged with module additions.
     *
     * @return array<string>
     */
    public static function getSearchable(): array
    {
        return array_values(array_unique(array_merge(
            static::$searchable,
            static::$additionalSearchable[static::class] ?? [],
        )));
    }
}
