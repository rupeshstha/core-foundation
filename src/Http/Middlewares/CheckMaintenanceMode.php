<?php

namespace CoreFoundation\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\Support\Maintenance\MaintenanceManager;

/**
 * CheckMaintenanceMode
 *
 * Intercepts requests to check if the system or current scope is under
 * maintenance.
 */
class CheckMaintenanceMode
{
    public function __construct(
        protected MaintenanceManager $manager
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $this->manager->check();

        return $next($request);
    }
}
