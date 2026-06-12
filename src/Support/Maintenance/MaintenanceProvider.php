<?php

namespace CoreFoundation\Support\Maintenance;

/**
 * MaintenanceProvider
 *
 * Interface for determining if the system (or a specific scope) is down
 * for maintenance.
 */
interface MaintenanceProvider
{
    /**
     * Check if the system is currently in maintenance mode.
     * 
     * @param string|null $scope Optional scope identifier (e.g. "tenant:1")
     */
    public function isDown(?string $scope = null): bool;

    /**
     * Get the maintenance data (retry_after, message, etc.).
     * 
     * @param string|null $scope
     * @return array{message: string, retry_after: int|null, reason: string|null}
     */
    public function data(?string $scope = null): array;
}
