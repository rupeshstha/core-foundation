<?php

namespace CoreFoundation\Entities;

use Illuminate\Database\Eloquent\Model;
use CoreFoundation\Traits\Models\ModelCastables;
use CoreFoundation\Traits\Models\ModelFillables;
use CoreFoundation\Traits\Models\ModelRelatable;
use CoreFoundation\Traits\Models\ModelScopeable;
use CoreFoundation\Traits\Models\ModelSearchable;
use CoreFoundation\Entities\Contracts\HasRelationRegistry;
use CoreFoundation\Entities\Contracts\HasSearchableColumns;

/**
 * BaseModel
 *
 * Foundation model for all Eloquent models in the application.
 * Composes four modular extensibility traits — all following the same pattern:
 * external modules register additions via static methods from their ServiceProviders.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ MODULAR EXTENSIBILITY — THE CORE PATTERN                                    │
 * │                                                                             │
 * │ In a modular monolith, Module B should never edit Module A's source.        │
 * │ Instead, Module B registers its additions from its own ServiceProvider.     │
 * │                                                                             │
 * │ All four extensibility systems follow the same API convention:              │
 * │                                                                             │
 * │   // In SubscriptionServiceProvider::boot():                                │
 * │   Order::addFillable(['subscription_id', 'plan_code']);                     │
 * │   Order::addCast(['subscription_id' => 'integer']);                         │
 * │   Order::addRelation('subscription', fn (Order $o) =>                       │
 * │       $o->hasOne(Subscription::class)                                       │
 * │   );                                                                        │
 * │   Order::addScope(new ActiveSubscriptionScope);                             │
 * │                                                                             │
 * │ The Order model is never touched. Module isolation is preserved.            │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ PRIMARY KEY                                                                 │
 * │                                                                             │
 * │ BaseModel has no opinion on primary key type. Child models declare their    │
 * │ own. Common patterns:                                                       │
 * │                                                                             │
 * │   // Auto-increment integer (Laravel default)                               │
 * │   class Order extends BaseModel { }                                         │
 * │                                                                             │
 * │   // UUID string                                                            │
 * │   class Order extends BaseModel                                             │
 * │   {                                                                         │
 * │       use HasUuids;                                                         │
 * │       protected $keyType  = 'string';                                       │
 * │       public    $incrementing = false;                                      │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ SOFT DELETES                                                                │
 * │                                                                             │
 * │ Not included by default. Add to child models that need it:                  │
 * │                                                                             │
 * │   class Order extends BaseModel                                             │
 * │   {                                                                         │
 * │       use SoftDeletes;                                                      │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ INTROSPECTION                                                               │
 * │                                                                             │
 * │   Order::getAdditionalFillable()   // fields added by modules               │
 * │   Order::getAdditionalCasts()      // casts added by modules                │
 * │   Order::getBindRelations()        // relations added by modules            │
 * │   Order::getAdditionalScopes()     // global scopes added by modules        │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
abstract class BaseModel extends Model implements HasRelationRegistry, HasSearchableColumns
{
    use ModelCastables;
    use ModelFillables;
    use ModelRelatable;
    use ModelScopeable;
    use ModelSearchable;

    /**
     * Re-apply all externally registered global scopes after model boot.
     * This ensures scopes added from ServiceProviders survive the boot cache
     * regardless of the order in which providers are resolved.
     */
    protected static function booted(): void
    {
        parent::booted();

        static::applyAdditionalScopes();
    }

    /**
     * Get relations bound via the modular extensibility registry.
     */
    public static function getBindRelations(): array
    {
        return static::$relationResolvers[static::class] ?? [];
    }
}
