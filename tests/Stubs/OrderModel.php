<?php

/**
 * =============================================================================
 * MODULAR MODEL EXTENSIBILITY EXAMPLE
 * =============================================================================
 *
 * Demonstrates how Module B (Subscriptions) extends Module A's (Orders)
 * model without touching Module A's source.
 *
 * File layout:
 *   modules/Orders/Entities/Order.php
 *   modules/Subscriptions/Entities/Subscription.php
 *   modules/Subscriptions/Scopes/ActiveSubscriptionScope.php
 *   modules/Subscriptions/Providers/SubscriptionServiceProvider.php
 */

// =============================================================================
// MODULE A — Order model (owns nothing subscription-related)
// =============================================================================

namespace Modules\Orders\Entities;

use CoreFoundation\Entities\BaseModel;

class Order extends BaseModel
{
    protected $fillable = [
        'user_id',
        'product',
        'amount',
        'currency',
        'status',
    ];

    protected $casts = [
        'amount' => 'float',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    // No knowledge of subscriptions. Module isolation preserved.
}

// =============================================================================
// MODULE B — Subscription model
// =============================================================================

namespace Modules\Subscriptions\Entities;

use Modules\Orders\Entities\Order;
use CoreFoundation\Entities\BaseModel;

class Subscription extends BaseModel
{
    protected $fillable = [
        'order_id',
        'plan_code',
        'status',
        'renews_at',
    ];

    protected $casts = [
        'renews_at' => 'datetime',
    ];

    public function order()
    {
        return $this->belongsTo(Order::class);
    }
}

// =============================================================================
// MODULE B — Global scope
// =============================================================================

namespace Modules\Subscriptions\Scopes;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;

class ActiveSubscriptionScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        // Only return orders that have an active subscription
        $builder->whereHas(
            'subscription',
            fn ($q) => $q->where('status', 'active')
        );
    }
}

// =============================================================================
// MODULE B — ServiceProvider wires everything together
// This is the ONLY file that touches Order — and it never edits Order's source.
// =============================================================================

namespace Modules\Subscriptions\Providers;

use Modules\Orders\Entities\Order;
use Illuminate\Support\ServiceProvider;
use Modules\Subscriptions\Entities\Subscription;
use Modules\Subscriptions\Scopes\ActiveSubscriptionScope;

class SubscriptionServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Extend Order's fillable — Module A's source never touched
        Order::addFillable([
            'subscription_id',
            'plan_code',
        ]);

        // Extend Order's casts
        Order::addCast([
            'subscription_id' => 'integer',
            'renews_at' => 'datetime',
        ]);

        // Extend Order's relations — $order->subscription works everywhere
        Order::addRelation(
            'subscription',
            fn (Order $order) => $order->hasOne(Subscription::class),
        );

        // Add a global scope — all Order queries filter by active subscription
        // Use string identifier so it can be removed with withoutGlobalScope()
        Order::addScope(new ActiveSubscriptionScope, 'active_subscription');
    }
}

// =============================================================================
// USAGE — application code, unaware of module boundaries
// =============================================================================

// Standard Eloquent — relation added by Module B works transparently
$order = Order::with('subscription')->find(1);
$order->subscription->plan_code;   // works
$order->subscription_id;           // fillable + cast by Module B

// Bypass the global scope when needed
$allOrders = Order::withoutGlobalScope('active_subscription')->get();

// Introspect what modules have added (useful for debugging, docs)
Order::getAdditionalFillable();   // ['subscription_id', 'plan_code']
Order::getAdditionalCasts();      // ['subscription_id' => 'integer', ...]
Order::getBindRelations();        // ['subscription' => Closure]
Order::getAdditionalScopes();     // ['active_subscription' => ActiveSubscriptionScope]
