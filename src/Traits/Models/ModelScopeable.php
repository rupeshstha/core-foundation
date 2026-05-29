<?php

namespace CoreFoundation\Traits\Models;

use Illuminate\Database\Eloquent\Scope;

/**
 * ModelScopeable
 *
 * Allows external modules to add Eloquent global scopes to a model without
 * modifying the model's source or its boot() method.
 *
 * Register from a module's ServiceProvider::boot():
 *
 *   Order::addScope(new ActiveSubscriptionScope);
 *   Order::addScope(new TenantScope);
 *
 * Or with a string identifier for explicit removal:
 *
 *   Order::addScope(new TenantScope, 'tenant');
 *   // Later: Order::withoutGlobalScope('tenant')
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ HOW IT WORKS                                                                │
 * │                                                                             │
 * │ Laravel's addGlobalScope() is already dynamic — it adds a scope to the     │
 * │ model's static scope registry at any time. The challenge is that global     │
 * │ scopes added after the model is booted don't automatically apply to         │
 * │ subsequent queries unless the boot cache is cleared.                        │
 * │                                                                             │
 * │ This trait handles that by tracking registered scopes and ensuring they     │
 * │ are applied via the model's booted() hook which fires once per request.     │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WRITING A SCOPE                                                             │
 * │                                                                             │
 * │   class TenantScope implements Scope                                        │
 * │   {                                                                         │
 * │       public function apply(Builder $builder, Model $model): void           │
 * │       {                                                                     │
 * │           $builder->where('tenant_id', app(TenantContext::class)->id());    │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait ModelScopeable
{
    /**
     * Registry of additional global scopes keyed by model class.
     *
     * @var array<class-string, array<string, Scope>>
     */
    protected static array $additionalScopes = [];

    /**
     * Add a global scope to this model from an external module.
     * Call from ServiceProvider::boot() — never from the model itself.
     *
     * @param  Scope  $scope  The scope instance to apply
     * @param  string|null  $identifier  Optional explicit identifier for withoutGlobalScope()
     *                                   Defaults to the scope's class name.
     */
    public static function addScope(Scope $scope, ?string $identifier = null): void
    {
        $identifier ??= get_class($scope);

        static::$additionalScopes[static::class][$identifier] = $scope;

        // Apply immediately via Laravel's native addGlobalScope.
        // Safe to call multiple times — Laravel deduplicates by identifier.
        static::addGlobalScope($identifier, $scope);
    }

    /**
     * Get all externally added global scopes for this model.
     *
     * @return array<string, Scope>
     */
    public static function getAdditionalScopes(): array
    {
        return static::$additionalScopes[static::class] ?? [];
    }

    /**
     * Remove a previously added external scope by identifier.
     * Clears from both the additional-scopes registry and Eloquent's internal
     * globalScopes registry so the scope no longer applies to any future query.
     */
    public static function removeScope(string $identifier): void
    {
        unset(static::$additionalScopes[static::class][$identifier]);

        // Also remove from Eloquent's internal static scope registry
        // so existing queries are not affected by stale scope entries.
        if (isset(static::$globalScopes[static::class][$identifier])) {
            unset(static::$globalScopes[static::class][$identifier]);
        }
    }

    /**
     * Re-apply all registered external scopes.
     * Called from BaseModel::booted() to ensure scopes survive model boot cache.
     */
    public static function applyAdditionalScopes(): void
    {
        foreach (static::getAdditionalScopes() as $identifier => $scope) {
            static::addGlobalScope($identifier, $scope);
        }
    }
}
