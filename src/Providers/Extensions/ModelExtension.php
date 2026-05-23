<?php

namespace CoreFoundation\Providers\Extensions;

use Closure;
use Illuminate\Database\Eloquent\Scope;

/**
 * ModelExtension
 *
 * Fluent builder for extending a BaseModel subclass from a module ServiceProvider.
 * Obtained via BaseExtensionServiceProvider::model() — never instantiated directly.
 *
 * USAGE:
 *
 *   $this->model(Order::class)
 *       ->fillable(['subscription_id', 'plan_code'])
 *       ->casts(['subscription_id' => 'integer', 'renews_at' => 'datetime'])
 *       ->relation('subscription', fn (Order $o) => $o->hasOne(Subscription::class))
 *       ->scope(new TenantScope, 'tenant')
 *       ->searchable(['subscription_id', 'plan_code']);
 */
final class ModelExtension
{
    public function __construct(private readonly string $model) {}

    /**
     * Add fillable fields to the model.
     *
     *   ->fillable(['subscription_id', 'plan_code'])
     */
    public function fillable(array $fields): static
    {
        ($this->model)::addFillable($fields);

        return $this;
    }

    /**
     * Add attribute casts to the model.
     *
     *   ->casts(['subscription_id' => 'integer', 'renews_at' => 'datetime'])
     */
    public function casts(array $casts): static
    {
        ($this->model)::addCast($casts);

        return $this;
    }

    /**
     * Bind an Eloquent relation to the model.
     * The resolver receives the model instance and must return a Relation.
     *
     *   ->relation('subscription', fn (Order $o) => $o->hasOne(Subscription::class))
     */
    public function relation(string $name, Closure $resolver): static
    {
        ($this->model)::addRelation($name, $resolver);

        return $this;
    }

    /**
     * Add a global scope to the model.
     * Pass an explicit identifier to enable withoutGlobalScope('identifier') later.
     *
     *   ->scope(new TenantScope)
     *   ->scope(new TenantScope, 'tenant')
     */
    public function scope(Scope $scope, ?string $identifier = null): static
    {
        ($this->model)::addScope($scope, $identifier);

        return $this;
    }

    /**
     * Declare columns as searchable/filterable for this model.
     * These columns are added to the FilterApplicator whitelist.
     *
     *   ->searchable(['subscription_id', 'plan_code'])
     */
    public function searchable(array $columns): static
    {
        ($this->model)::addSearchable($columns);

        return $this;
    }
}
