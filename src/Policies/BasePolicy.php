<?php

namespace CoreFoundation\Policies;

use Illuminate\Auth\Access\Response;
use Illuminate\Database\Eloquent\Model;

/**
 * BasePolicy
 *
 * Abstract authorization policy base. All standard CRUD abilities deny by default —
 * developers opt in by overriding only the abilities they intend to grant.
 *
 * DENY-BY-DEFAULT:
 *
 * Not overriding an ability = access denied. This is the secure baseline for
 * enterprise APIs. An accidental omission is a denial, not an open gate.
 *
 * USAGE:
 *
 *   class OrderPolicy extends BasePolicy
 *   {
 *       public function viewAny(mixed $user): Response|bool
 *       {
 *           return $user->hasPermission('orders.list');
 *       }
 *
 *       public function view(mixed $user, Model $model): Response|bool
 *       {
 *           return $user->id === $model->user_id
 *               ? Response::allow()
 *               : Response::deny('You do not own this order.');
 *       }
 *
 *       public function create(mixed $user): Response|bool
 *       {
 *           return $user->hasPermission('orders.create');
 *       }
 *   }
 *
 * REGISTRATION (in module ServiceProvider):
 *
 *   Gate::policy(Order::class, OrderPolicy::class);
 *
 * AUTHORIZATION IN CONTROLLERS:
 *
 *   $this->authorize('view', $order);       // throws 403 on denial
 *   $this->authorize('viewAny', Order::class);
 *
 * PHPDoc GENERIC TYPES (for IDE type safety — not enforced at runtime):
 *
 *   @template TUser of \Illuminate\Contracts\Auth\Authenticatable
 *   @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * Declare on your concrete policy so IDEs resolve the correct types:
 *
 *   @extends BasePolicy<User, Order>
 *
 * Then inside your methods, cast the param if your IDE needs it:
 *
 *   /** @var User $user {@*}  /** @var Order $model {@*}
 *
 * WHY $model IS TYPED Model, NOT YOUR CONCRETE CLASS:
 *
 * PHP parameter types are contravariant — an override may only WIDEN a parent's
 * parameter type, never narrow it. Declaring `view(mixed $user, Order $model)` in
 * a subclass is a fatal "Declaration must be compatible" error, because `Order` is
 * narrower than this class's `Model`. Keep the parameter typed `Model` and use the
 * `@var` cast above (or the `@template` below) for IDE-only narrowing instead.
 *
 * @template TUser of \Illuminate\Contracts\Auth\Authenticatable
 * @template TModel of \Illuminate\Database\Eloquent\Model
 */
abstract class BasePolicy
{
    // =========================================================================
    // Model-free abilities (actor only)
    // =========================================================================

    /**
     * Can the actor list any records of this resource?
     *
     * @param  TUser  $user
     */
    public function viewAny(mixed $user): Response|bool
    {
        return Response::deny();
    }

    /**
     * Can the actor create a new record of this resource?
     *
     * @param  TUser  $user
     */
    public function create(mixed $user): Response|bool
    {
        return Response::deny();
    }

    // =========================================================================
    // Model-bound abilities (actor + target record)
    // =========================================================================

    /**
     * Can the actor view this specific record?
     *
     * @param  TUser  $user
     * @param  TModel  $model
     */
    public function view(mixed $user, Model $model): Response|bool
    {
        return Response::deny();
    }

    /**
     * Can the actor update this specific record?
     *
     * @param  TUser  $user
     * @param  TModel  $model
     */
    public function update(mixed $user, Model $model): Response|bool
    {
        return Response::deny();
    }

    /**
     * Can the actor delete this specific record?
     *
     * @param  TUser  $user
     * @param  TModel  $model
     */
    public function delete(mixed $user, Model $model): Response|bool
    {
        return Response::deny();
    }

    // =========================================================================
    // Soft-delete abilities — override only if the model uses SoftDeletes
    // =========================================================================

    /**
     * Can the actor restore a soft-deleted record?
     *
     * @param  TUser  $user
     * @param  TModel  $model
     */
    public function restore(mixed $user, Model $model): Response|bool
    {
        return Response::deny();
    }

    /**
     * Can the actor permanently delete a soft-deleted record?
     *
     * @param  TUser  $user
     * @param  TModel  $model
     */
    public function forceDelete(mixed $user, Model $model): Response|bool
    {
        return Response::deny();
    }
}
