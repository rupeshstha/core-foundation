<?php

namespace CoreFoundation\Support\Maintenance;

use Closure;
use CoreFoundation\Exceptions\MaintenanceModeException;

/**
 * MaintenanceManager
 *
 * Orchestrates maintenance mode checks using the registered provider
 * and scope resolver.
 */
class MaintenanceManager
{
    protected ?MaintenanceProvider $provider = null;

    protected ?Closure $scopeResolver = null;

    /**
     * Register the maintenance provider.
     */
    public function setProvider(MaintenanceProvider $provider): void
    {
        $this->provider = $provider;
    }

    /**
     * Set a closure to resolve the current maintenance scope (e.g. current tenant ID).
     */
    public function resolveScopeUsing(Closure $resolver): void
    {
        $this->scopeResolver = $resolver;
    }

    /**
     * Check if maintenance mode is active and throw if it is.
     * 
     * @throws MaintenanceModeException
     */
    public function check(): void
    {
        if (! $this->provider) {
            return;
        }

        $scope = $this->scopeResolver ? ($this->scopeResolver)() : null;

        if ($this->provider->isDown($scope)) {
            $data = $this->provider->data($scope);

            throw new MaintenanceModeException(
                $data['message'] ?? 'Service Under Maintenance',
                $data['retry_after'] ?? null,
                $data['reason'] ?? null
            );
        }
    }
}
