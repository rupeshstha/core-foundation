<?php

namespace CoreFoundation\Traits\Models;

use Closure;

/**
 * ModelRelatable
 *
 * Allows external modules to add Eloquent relations to a model without
 * modifying the model's source.
 *
 * Wraps Laravel's native Model::resolveRelationUsing() with a consistent
 * API that mirrors addFillable() and addCast() — and exposes the registry
 * for introspection.
 *
 * Register from a module's ServiceProvider::boot():
 *
 *   Order::addRelation('subscription', function (Order $order) {
 *       return $order->hasOne(Subscription::class);
 *   });
 *
 *   Order::addRelation('plan', function (Order $order) {
 *       return $order->belongsTo(Plan::class, 'plan_code', 'code');
 *   });
 *
 * Usage in application code — identical to a natively defined relation:
 *
 *   $order->subscription;
 *   $order->load('subscription');
 *   Order::with('subscription')->get();
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ HOW IT WORKS                                                                │
 * │                                                                             │
 * │ resolveRelationUsing() is Laravel's own mechanism — it registers a closure  │
 * │ that Eloquent calls when the relation name is accessed. This trait wraps    │
 * │ it with a registry so you can introspect which relations have been bound    │
 * │ (useful for eager loading validation, documentation, debugging).            │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait ModelRelatable
{
    /**
     * Registry of externally bound relations keyed by model class.
     * Mirrors the shape of Eloquent's internal $relationResolvers.
     *
     * @var array<class-string, array<string, Closure>>
     */
    protected static array $boundRelations = [];

    /**
     * Register an external relation on this model.
     * Call from ServiceProvider::boot() — never from the model itself.
     *
     * @param  string  $name  The relation name — accessed as $model->name
     * @param  Closure  $resolver  Receives the model instance, returns a Relation
     */
    public static function addRelation(string $name, Closure $resolver): void
    {
        // Register with Laravel's native relation resolver — this is what
        // makes $order->subscription work as a standard Eloquent relation.
        static::resolveRelationUsing($name, $resolver);

        // Also track in our own registry for introspection.
        static::$boundRelations[static::class][$name] = $resolver;
    }

    /**
     * Get all externally bound relations for this model.
     * Returns the registry keyed by relation name.
     *
     * @return array<string, Closure>
     */
    public static function getBindRelations(): array
    {
        return static::$boundRelations[static::class] ?? [];
    }

    /**
     * Check whether an external relation has been bound under the given name.
     */
    public static function hasBindRelation(string $name): bool
    {
        return isset(static::$boundRelations[static::class][$name]);
    }
}
