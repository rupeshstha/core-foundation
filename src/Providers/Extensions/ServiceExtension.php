<?php

namespace CoreFoundation\Providers\Extensions;

use Closure;

/**
 * ServiceExtension
 *
 * Fluent builder for extending a BaseService subclass from a module ServiceProvider.
 * Obtained via BaseExtensionServiceProvider::service() — never instantiated directly.
 *
 * USAGE:
 *
 *   $this->service(OrderService::class)
 *       ->pipe('place', ValidateSubscriptionPipe::class)
 *       ->pipe('place', EnrichWithPlanPipe::class)
 *       ->prefer(SubscriptionOrderService::class, fn () => config('subscription.active'));
 *
 * NOTE — activating a preference requires a container binding in register():
 *
 *   protected function registerBindings(): void
 *   {
 *       $this->app->bind(OrderService::class, fn () => (new OrderService)->resolvePreference());
 *   }
 */
final class ServiceExtension
{
    public function __construct(private readonly string $service) {}

    /**
     * Register a pipe against a named hook on the service.
     * Pipes run in the order they are registered across all ServiceProviders.
     *
     *   ->pipe('place', ValidateSubscriptionPipe::class)
     *   ->pipe('place', ApplyDiscountPipe::class)
     */
    public function pipe(string $hook, string $pipeClass): static
    {
        ($this->service)::addPipe($hook, $pipeClass);

        return $this;
    }

    /**
     * Register a conditional class preference for the service.
     *
     * The concrete must extend the service it replaces (enforced by HasFactory at resolution time).
     * Conditions must be cheap — config reads and feature flags only, no DB calls.
     *
     *   ->prefer(SubscriptionOrderService::class, fn () => config('subscription.active'))
     */
    public function prefer(string $concrete, Closure|string $condition): static
    {
        ($this->service)::setPreference($concrete, $condition);

        return $this;
    }
}
