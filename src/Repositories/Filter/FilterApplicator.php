<?php

namespace CoreFoundation\Repositories\Filter;

use InvalidArgumentException;
use Illuminate\Database\Eloquent\Builder;
use CoreFoundation\Repositories\Filter\Contracts\FilterOperator;

/**
 * FilterApplicator
 *
 * Applies request filter parameters to an Eloquent Builder using a config-driven
 * operator registry. Operators are identified by a `__xxx_` prefix on query keys.
 *
 * Registered as a singleton — stateless instance, static registry persists across
 * requests (Octane-safe). Configuration: ServiceProvider → static API → apply().
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ OPERATOR REGISTRY — three ways to customise                                 │
 * │                                                                             │
 * │ 1. Config (config/repository.php) — the base set:                           │
 * │      'operators' => [                                                       │
 * │          '__like_'    => CustomLikeOperator::class,  // replace built-in   │
 * │          '__null_'    => null,                        // disable operator   │
 * │          '__between_' => BetweenOperator::class,      // add custom         │
 * │      ]                                                                      │
 * │                                                                             │
 * │ 2. ServiceProvider::boot() — programmatic overrides:                        │
 * │      FilterApplicator::addOperator(new BetweenOperator);                    │
 * │      FilterApplicator::removeOperator('__null_');                           │
 * │      FilterApplicator::overrideOperator('__like_', new CustomLikeOperator); │
 * │   Pre-boot registrations are preserved and take precedence over config.    │
 * │                                                                             │
 * │ 3. Per-repository — pass a custom operator set to apply() directly          │
 * │    by overriding the repository's filter invocation.                        │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ FILTER FORMAT — query params as received after $request->query()            │
 * │                                                                             │
 * │   Flat operators:                                                           │
 * │   '__eq_name'        => 'John'       WHERE name = 'John'                   │
 * │   '__like_email'     => '@gmail'     WHERE email LIKE '%@gmail%'            │
 * │   '__gt_age'         => 18           WHERE age > 18                        │
 * │   '__in_status'      => ['a', 'b']   WHERE status IN ('a', 'b')            │
 * │   '__null_deleted_at' => 1           WHERE deleted_at IS NULL               │
 * │   '__nnull_deleted_at' => 1          WHERE deleted_at IS NOT NULL           │
 * │                                                                             │
 * │   Nested boolean groups:                                                    │
 * │   '__or_'  => [['__eq_status' => 'a'], ['__eq_status' => 'b']]             │
 * │              WHERE (status = 'a' OR status = 'b')                          │
 * │                                                                             │
 * │   '__and_' => [['__gte_price' => 10], ['__lte_price' => 100]]              │
 * │              WHERE (price >= 10 AND price <= 100)                           │
 * │                                                                             │
 * │   __andor_ groups (opt-in, not in default config):                          │
 * │   '__andor_' => [[...or-conditions...], [...or-conditions...]]              │
 * │              WHERE (A OR B) AND (C OR D)                                   │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ SECURITY — column whitelist enforcement                                     │
 * │                                                                             │
 * │ Every flat filter column is validated against the $allowedColumns array     │
 * │ (the repository's searchable() list) before any SQL is generated.           │
 * │ Columns not in the whitelist are silently skipped — never thrown.           │
 * │ Unknown operators are also skipped silently.                                │
 * │                                                                             │
 * │ This prevents SQL injection via filter keys regardless of what a client     │
 * │ sends. Nested operators (or/and) inherit the same whitelist.                │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WRITING A CUSTOM OPERATOR                                                   │
 * │                                                                             │
 * │   final class BetweenOperator implements FilterOperator                     │
 * │   {                                                                         │
 * │       public function identifier(): string { return '__between_'; }         │
 * │                                                                             │
 * │       public function apply(Builder $builder, string $column, mixed $value): void
 * │       {                                                                     │
 * │           [$min, $max] = (array) $value;                                    │
 * │           $builder->whereBetween($column, [$min, $max]);                    │
 * │       }                                                                     │
 * │   }                                                                         │
 * │                                                                             │
 * │   // Register in ServiceProvider::boot():                                   │
 * │   FilterApplicator::addOperator(new BetweenOperator);                       │
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

    /**
     * Apply filter parameters to the given Builder.
     *
     * @param  Builder  $builder  The query to filter
     * @param  array  $filters  Raw filter params: ['__eq_name' => 'John', ...]
     * @param  array<string>  $allowedColumns  Searchable column whitelist — SQL injection guard
     * @param  string  $boolean  Join boolean for top-level filters ('and' or 'or')
     */
    public function apply(Builder $builder, array $filters, array $allowedColumns, string $boolean = 'and'): Builder
    {
        self::boot();

        foreach ($filters as $key => $value) {
            $this->applyFilter($builder, (string) $key, $value, $allowedColumns, $boolean);
        }

        return $builder;
    }

    private function applyFilter(
        Builder $builder,
        string $key,
        mixed $value,
        array $allowedColumns,
        string $boolean = 'and',
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
            $this->applyNestedCondition($builder, $value, $allowedColumns, 'or');

            return;
        }

        if ($identifier === '__and_' && is_array($value)) {
            $this->applyNestedCondition($builder, $value, $allowedColumns, 'and');

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

        // We wrap the operator application in a where() call to ensure the correct boolean joining
        $builder->where(fn ($q) => $operator->apply($q, $column, $value), null, null, $boolean);
    }

    private function applyNestedCondition(
        Builder $builder,
        array $filters,
        array $allowedColumns,
        string $boolean,
    ): void {
        $builder->where(function (Builder $nested) use ($filters, $allowedColumns, $boolean) {
            // For nested conditions, we join the children with the group's boolean
            $this->apply($nested, $filters, $allowedColumns, $boolean);
        }, null, null, $boolean);
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

    /**
     * Initialize the operator registry from config/repository.php.
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
