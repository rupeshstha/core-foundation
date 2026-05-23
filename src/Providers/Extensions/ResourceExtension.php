<?php

namespace CoreFoundation\Providers\Extensions;

use Closure;

/**
 * ResourceExtension
 *
 * Fluent builder for extending a BaseResource subclass from a module ServiceProvider.
 * Obtained via BaseExtensionServiceProvider::resource() — never instantiated directly.
 *
 * USAGE:
 *
 *   $this->resource(UserResource::class)
 *       ->field('subscription_status', fn ($user, $req) => $user->subscription?->status)
 *       ->field('email', fn ($user, $req) => $req->user()?->isAdmin() ? $user->email : '***')
 *       ->remove('internal_notes');
 */
final class ResourceExtension
{
    public function __construct(private readonly string $resource) {}

    /**
     * Add or override a response field.
     *
     * The resolver receives the raw underlying resource and the current Request.
     * If the key already exists in the resource's fields(), the registered value replaces it.
     *
     *   ->field('plan', fn ($order, $req) => $order->subscription?->plan_code)
     *   ->field('email', fn ($user, $req) => $req->user()?->isAdmin() ? $user->email : '***')
     */
    public function field(string $key, Closure $resolver): static
    {
        ($this->resource)::addField($key, $resolver);

        return $this;
    }

    /**
     * Remove a field from the response entirely.
     *
     * Removals are applied after all additions — they always win.
     *
     *   ->remove('internal_notes')
     *   ->remove('ssn')
     */
    public function remove(string $key): static
    {
        ($this->resource)::removeField($key);

        return $this;
    }
}
