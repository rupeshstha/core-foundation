<?php

namespace CoreFoundation\Repositories\Filter;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;
use CoreFoundation\Repositories\Filter\Contracts\FilterOperator;

/**
 * FilterApplicator
 *
 * Applies request filter parameters to an Eloquent Builder.
 *
 * The operator registry is driven by config/repository.php (operators key).
 * Operators can be overridden, disabled, or extended via config or the
 * programmatic API (addOperator / removeOperator) from ServiceProviders.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ OPERATOR REGISTRY — three ways to customise                                 │
 * │                                                                             │
 * │ 1. Config override (config/repository.php):                                 │
 * │      '__like_' => CustomLikeOperator::class,  // replace built-in          │
 * │      '__null_' => null,                        // disable                  │
 * │      '__between_' => BetweenOperator::class,   // add new                  │
 * │                                                                             │
 * │ 2. ServiceProvider::boot() — programmatic:                                  │
 * │      FilterApplicator::addOperator(new BetweenOperator);                    │
 * │      FilterApplicator::removeOperator('__null_');                           │
 * │      FilterApplicator::overrideOperator('__like_', new CustomLikeOperator); │
 * │                                                                             │
 * │ 3. Per-repository — override resolveOperators() in the concrete repo        │
 * │    to return a completely custom set just for that resource.                │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ FILTER FORMAT                                                               │
 * │                                                                             │
 * │   __eq_name=John           WHERE name = 'John'                             │
 * │   __like_email=@gmail      WHERE email LIKE '%@gmail%'                     │
 * │   __gt_age=18              WHERE age > 18                                  │
 * │   __in_status[]=a&[]=b     WHERE status IN ('a','b')                       │
 * │   __null_deleted_at=1      WHERE deleted_at IS NULL                        │
 * │   __or_[__eq_s]=a&[__eq_s]=b   WHERE (s='a' OR s='b')                     │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WRITING A CUSTOM OPERATOR                                                   │
 * │                                                                             │
 * │   class BetweenOperator implements FilterOperator                           │
 * │   {                                                                         │
 * │       public function identifier(): string { return '__between_'; }         │
 * │                                                                             │
 * │       public function apply(Builder $builder, string $column, mixed $value): void
 * │       {                                                                     │
 * │           // $value expected as [min, max]                                  │
 * │           $builder->whereBetween($column, (array) $value);                  │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
final class FilterApplicator
{
    /**
     * Runtime operator registry — built from config on first use.
     * Static so ServiceProvider registrations persist for the request lifetime.
     * Keyed by identifier string.
     *
     * @var array<string, FilterOperator>
     */
    private static array $registry = [];

    /**
     * Whether the registry has been initialised from config.
     */
    private static bool $booted = false;

    // =========================================================================
    // Programmatic registry API — call from ServiceProvider::boot()
    // =========================================================================

    /**
     * Add or replace an operator at runtime.
     * Merges over any config-defined operator with the same identifier.
     */
    public static function addOperator(FilterOperator $operator): void
    {
        self::$registry[$operator->identifier()] = $operator;
    }

    /**
     * Remove an operator from the registry by identifier.
     * Use to disable a built-in operator for all repositories.
     */
    public static function removeOperator(string $identifier): void
    {
        unset(self::$registry[$identifier]);
    }

    /**
     * Replace an existing operator with a different implementation.
     * Throws if the identifier is not registered — prevents silent typos.
     */
    public static function overrideOperator(string $identifier, FilterOperator $operator): void
    {
        self::boot();

        if (! isset(self::$registry[$identifier])) {
            throw new InvalidArgumentException(
                "Cannot override operator [{$identifier}]: not found in registry. "
                .'Use addOperator() to register a new operator.'
            );
        }

        self::$registry[$identifier] = $operator;
    }

    /**
     * Return all currently registered operators.
     * Useful for debugging and testing.
     *
     * @return array<string, FilterOperator>
     */
    public static function operators(): array
    {
        self::boot();

        return self::$registry;
    }

    /**
     * Reset the registry to the config-defined defaults.
     * Useful in tests to isolate between cases.
     */
    public static function reset(): void
    {
        self::$registry = [];
        self::$booted = false;
    }

    // =========================================================================
    // Application
    // =========================================================================

    /**
     * Apply filter parameters to the given Builder.
     *
     * @param  Builder  $builder  The query to filter
     * @param  array  $filters  Raw filter params: ['__eq_name' => 'John', ...]
     * @param  array<string>  $allowedColumns  Searchable column whitelist — SQL injection guard
     */
    public function apply(Builder $builder, array $filters, array $allowedColumns): Builder
    {
        self::boot();

        foreach ($filters as $key => $value) {
            $this->applyFilter($builder, (string) $key, $value, $allowedColumns);
        }

        return $builder;
    }

    // =========================================================================
    // Internals
    // =========================================================================

    private function applyFilter(
        Builder $builder,
        string $key,
        mixed $value,
        array $allowedColumns,
    ): void {
        $identifier = $this->extractIdentifier($key);

        if ($identifier === null) {
            return;
        }

        if (! array_key_exists($identifier, self::$registry)) {
            return; // Unknown or disabled operator — skip silently
        }

        $operator = self::$registry[$identifier];

        // Nested OR/AND — recurse without column resolution
        if ($identifier === '__or_' && is_array($value)) {
            $this->applyNestedCondition($builder, $value, $allowedColumns, 'orWhere');

            return;
        }

        if ($identifier === '__and_' && is_array($value)) {
            $this->applyNestedCondition($builder, $value, $allowedColumns, 'where');

            return;
        }

        // AndOr groups — delegate entirely to the operator, no column resolution needed
        if ($identifier === '__andor_' && is_array($value)) {
            $operator->apply($builder, '', $value);

            return;
        }

        $column = str_replace($identifier, '', $key);

        // Security — reject columns not in the searchable whitelist
        if (! in_array($column, $allowedColumns, true)) {
            return;
        }

        $operator->apply($builder, $column, $value);
    }

    private function applyNestedCondition(
        Builder $builder,
        array $filters,
        array $allowedColumns,
        string $method,
    ): void {
        $builder->{$method}(function (Builder $nested) use ($filters, $allowedColumns) {
            $this->apply($nested, $filters, $allowedColumns);
        });
    }

    /**
     * Extract the __xxx_ operator identifier prefix from a filter key.
     * Returns null when the key does not match the expected pattern.
     */
    private function extractIdentifier(string $key): ?string
    {
        if (! str_starts_with($key, '__')) {
            return null;
        }

        preg_match('/^(__[a-z]+_)/', $key, $matches);

        return $matches[1] ?? null;
    }

    // =========================================================================
    // Boot — build registry from config on first use
    // =========================================================================

    /**
     * Initialise the operator registry from config/repository.php.
     *
     * Config structure:
     *   'operators' => [
     *       '__eq_'      => EqualOperator::class,   // registered
     *       '__null_'    => null,                    // disabled
     *       '__between_' => BetweenOperator::class,  // custom
     *   ]
     *
     * Runtime addOperator() calls made before boot() also survive —
     * they are merged after the config pass so they take precedence.
     */
    private static function boot(): void
    {
        if (self::$booted) {
            return;
        }

        // Preserve any operators registered before boot (e.g. early ServiceProviders)
        $preBootRegistry = self::$registry;

        self::$registry = [];

        $configOperators = config('repository.operators', []);

        foreach ($configOperators as $identifier => $class) {
            // null in config means the operator is intentionally disabled
            if ($class === null) {
                continue;
            }

            if (! class_exists($class)) {
                throw new InvalidArgumentException(
                    "Operator class [{$class}] for identifier [{$identifier}] does not exist. "
                    .'Check config/repository.php operators.'
                );
            }

            $instance = app($class);

            if (! $instance instanceof FilterOperator) {
                throw new InvalidArgumentException(
                    "Operator class [{$class}] must implement FilterOperator."
                );
            }

            // Use identifier from config key, not from the class — allows
            // one class to serve multiple identifiers if needed
            self::$registry[$identifier] = $instance;
        }

        // Merge pre-boot registrations — they take precedence over config
        foreach ($preBootRegistry as $identifier => $operator) {
            self::$registry[$identifier] = $operator;
        }

        self::$booted = true;
    }
}
