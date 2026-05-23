<?php

namespace CoreFoundation\Providers;

use LogicException;
use CoreFoundation\Entities\BaseModel;
use Illuminate\Support\ServiceProvider;
use CoreFoundation\Services\BaseService;
use CoreFoundation\Transformers\BaseResource;
use CoreFoundation\Providers\Extensions\ModelExtension;
use CoreFoundation\Repositories\Filter\FilterApplicator;
use CoreFoundation\Providers\Extensions\ServiceExtension;
use CoreFoundation\Providers\Extensions\ResourceExtension;
use CoreFoundation\Repositories\Filter\Contracts\FilterOperator;

/**
 * BaseExtensionServiceProvider
 *
 * Structured ServiceProvider base for modules that extend CoreFoundation base classes.
 * Replaces a flat wall of static calls in boot() with named, grouped extension hooks.
 *
 * BEFORE (raw static calls — no structure, no discoverability):
 *
 *   public function boot(): void
 *   {
 *       Order::addFillable(['subscription_id']);
 *       Order::addRelation('subscription', fn ($o) => $o->hasOne(Subscription::class));
 *       UserResource::addField('plan', fn ($u, $r) => $u->subscription?->plan_code);
 *       OrderService::addPipe('place', ValidateSubscriptionPipe::class);
 *       FilterApplicator::addOperator(new BetweenDateOperator);
 *   }
 *
 * AFTER (structured hooks — grouped, typed, discoverable):
 *
 *   class SubscriptionServiceProvider extends BaseExtensionServiceProvider
 *   {
 *       protected function registerBindings(): void
 *       {
 *           $this->app->bind(OrderService::class, fn () => (new OrderService)->resolvePreference());
 *       }
 *
 *       protected function extendModels(): void
 *       {
 *           $this->model(Order::class)
 *               ->fillable(['subscription_id', 'plan_code'])
 *               ->casts(['subscription_id' => 'integer', 'renews_at' => 'datetime'])
 *               ->relation('subscription', fn ($o) => $o->hasOne(Subscription::class))
 *               ->searchable(['subscription_id']);
 *       }
 *
 *       protected function extendResources(): void
 *       {
 *           $this->resource(UserResource::class)
 *               ->field('plan', fn ($u, $r) => $u->subscription?->plan_code)
 *               ->remove('raw_plan_data');
 *       }
 *
 *       protected function extendServices(): void
 *       {
 *           $this->service(OrderService::class)
 *               ->pipe('place', ValidateSubscriptionPipe::class)
 *               ->prefer(SubscriptionOrderService::class, fn () => config('subscription.active'));
 *       }
 *
 *       protected function extendOperators(): void
 *       {
 *           $this->operator(new BetweenDateOperator);
 *       }
 *   }
 *
 * ADDITIONAL BOOT / REGISTER WORK:
 *
 * Use the structured hooks (registerBindings, extendModels, etc.) whenever possible.
 * For everything else (routes, views, commands), override the lifecycle method and
 * call parent — the hooks will still run:
 *
 *   public function boot(): void
 *   {
 *       parent::boot();
 *       $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
 *   }
 */
abstract class BaseExtensionServiceProvider extends ServiceProvider
{
    // =========================================================================
    // Lifecycle — override and call parent when you need additional work
    // =========================================================================

    /**
     * Called by Laravel during the registration phase.
     * Override registerBindings() for container bindings — no parent call needed.
     */
    public function register(): void
    {
        $this->registerBindings();
    }

    /**
     * Called by Laravel during the boot phase.
     * Override individual extension hooks instead of this method when possible.
     * When overriding, always call parent::boot() first.
     */
    public function boot(): void
    {
        $this->extendModels();
        $this->extendResources();
        $this->extendServices();
        $this->extendOperators();
    }

    // =========================================================================
    // Extension hooks — override any subset, all are no-op by default
    // =========================================================================

    /**
     * Register container bindings for this module.
     * Runs during register() — before any boot() calls.
     *
     *   protected function registerBindings(): void
     *   {
     *       $this->app->bind(OrderService::class, fn () => (new OrderService)->resolvePreference());
     *   }
     */
    protected function registerBindings(): void {}

    /**
     * Extend BaseModel subclasses — fillable, casts, relations, scopes, searchable.
     *
     *   protected function extendModels(): void
     *   {
     *       $this->model(Order::class)
     *           ->fillable(['subscription_id'])
     *           ->relation('subscription', fn ($o) => $o->hasOne(Subscription::class));
     *   }
     */
    protected function extendModels(): void {}

    /**
     * Extend BaseResource subclasses — add, override, or remove response fields.
     *
     *   protected function extendResources(): void
     *   {
     *       $this->resource(OrderResource::class)
     *           ->field('plan_code', fn ($o, $r) => $o->subscription?->plan_code)
     *           ->remove('internal_id');
     *   }
     */
    protected function extendResources(): void {}

    /**
     * Extend BaseService subclasses — pipes and preferences.
     *
     *   protected function extendServices(): void
     *   {
     *       $this->service(OrderService::class)
     *           ->pipe('place', ValidateSubscriptionPipe::class)
     *           ->prefer(SubscriptionOrderService::class, fn () => config('subscription.active'));
     *   }
     */
    protected function extendServices(): void {}

    /**
     * Register custom filter operators with FilterApplicator.
     *
     *   protected function extendOperators(): void
     *   {
     *       $this->operator(new BetweenDateOperator);
     *       $this->operator(new RelativeDateOperator);
     *   }
     */
    protected function extendOperators(): void {}

    // =========================================================================
    // Fluent factories — type-validated, return chainable builders
    // =========================================================================

    /**
     * Begin extending a BaseModel subclass.
     *
     * @param  class-string<BaseModel>  $model
     *
     * @throws LogicException if $model does not extend BaseModel.
     */
    final protected function model(string $model): ModelExtension
    {
        if (! class_exists($model) || ! is_a($model, BaseModel::class, true)) {
            throw new LogicException(
                "[{$model}] must extend ".BaseModel::class.'.'
            );
        }

        return new ModelExtension($model);
    }

    /**
     * Begin extending a BaseResource subclass.
     *
     * @param  class-string<BaseResource>  $resource
     *
     * @throws LogicException if $resource does not extend BaseResource.
     */
    final protected function resource(string $resource): ResourceExtension
    {
        if (! class_exists($resource) || ! is_a($resource, BaseResource::class, true)) {
            throw new LogicException(
                "[{$resource}] must extend ".BaseResource::class.'.'
            );
        }

        return new ResourceExtension($resource);
    }

    /**
     * Begin extending a BaseService subclass.
     *
     * @param  class-string<BaseService>  $service
     *
     * @throws LogicException if $service does not extend BaseService.
     */
    final protected function service(string $service): ServiceExtension
    {
        if (! class_exists($service) || ! is_a($service, BaseService::class, true)) {
            throw new LogicException(
                "[{$service}] must extend ".BaseService::class.'.'
            );
        }

        return new ServiceExtension($service);
    }

    /**
     * Register a custom filter operator globally with FilterApplicator.
     *
     *   $this->operator(new BetweenDateOperator);
     */
    final protected function operator(FilterOperator $operator): void
    {
        FilterApplicator::addOperator($operator);
    }
}
