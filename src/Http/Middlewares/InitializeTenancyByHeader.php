<?php

namespace CoreFoundation\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Stancl\Tenancy\Tenancy;
use Symfony\Component\HttpFoundation\Response;
use Stancl\Tenancy\Resolvers\DomainTenantResolver;
use Stancl\Tenancy\Middleware\IdentificationMiddleware;

/**
 * InitializeTenancyByHeader
 *
 * Resolves the tenant using a custom HTTP header (default: X-Tenant).
 *
 * This is preferred for API-first applications where the client may not be
 * running on a tenant-specific subdomain, or when an AI agent is making
 * calls on behalf of a merchant and provides the tenant context explicitly.
 *
 * Example:
 *   X-Tenant: merchant-a
 */
class InitializeTenancyByHeader extends IdentificationMiddleware
{
    /** @var Tenancy */
    protected $tenancy;

    /** @var DomainTenantResolver */
    protected $resolver;

    public function __construct(Tenancy $tenancy, DomainTenantResolver $resolver)
    {
        $this->tenancy = $tenancy;
        $this->resolver = $resolver;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): Response
    {
        $headerName = config('core-foundation.tenancy.header', 'X-Tenant');
        $tenantId = $request->header($headerName);

        if ($tenantId) {
            // Security: Prevent spoofing unless from a trusted source (Gateway/Proxy)
            if (config('core-foundation.tenancy.require_trusted_source') && 
                ! in_array($request->ip(), config('core-foundation.tenancy.trusted_sources'))) {
                abort(403, 'Untrusted tenancy identification source.');
            }

            return $this->initializeTenancy($request, $next, $tenantId);
        }

        return $next($request);
    }
}
