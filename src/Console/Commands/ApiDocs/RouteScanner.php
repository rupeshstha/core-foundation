<?php

namespace CoreFoundation\Console\ApiDocs;

use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as RouteFacade;

/**
 * RouteScanner
 *
 * Scans Laravel's route collection and returns routes eligible for documentation.
 *
 * Eligibility rules (all must pass):
 *  - URI matches the configured prefix filter (default: 'api')
 *  - Route has a controller action (not a closure)
 *  - Route is not in the configured exclude list
 *  - Route HTTP method is one of: GET, POST, PUT, PATCH, DELETE
 */
class RouteScanner
{
    private array $config;

    public function __construct(array $config)
    {
        $this->config = $config;
    }

    /**
     * @return array<ScannedRoute>
     */
    public function scan(): array
    {
        $routes = RouteFacade::getRoutes()->getRoutes();
        $scanned = [];

        foreach ($routes as $route) {
            if (! $this->isEligible($route)) {
                continue;
            }

            foreach ($this->httpMethods($route) as $method) {
                $scanned[] = new ScannedRoute(
                    method: $method,
                    uri: '/'.ltrim($route->uri(), '/'),
                    action: $route->getActionName(),
                    name: $route->getName() ?? '',
                    middleware: $route->middleware(),
                );
            }
        }

        // Merge manual routes from config — they supplement or override auto-scan
        foreach ($this->config['routes'] ?? [] as $manual) {
            $scanned[] = new ScannedRoute(
                method: strtoupper($manual['method']),
                uri: $manual['uri'],
                action: $manual['action'],
                name: $manual['name'] ?? '',
                middleware: $manual['middleware'] ?? [],
            );
        }

        return $scanned;
    }

    private function isEligible(Route $route): bool
    {
        // Must match the URI prefix filter
        $prefix = $this->config['prefix'] ?? 'api';
        if (! str_starts_with(ltrim($route->uri(), '/'), ltrim($prefix, '/'))) {
            return false;
        }

        // Must be a controller action, not a closure
        $action = $route->getActionName();
        if ($action === 'Closure' || ! str_contains($action, '@')) {
            return false;
        }

        // Must not be in the exclude list
        $excludes = $this->config['exclude'] ?? [];
        if (in_array($route->getName(), $excludes, true)) {
            return false;
        }

        return true;
    }

    private function httpMethods(Route $route): array
    {
        $allowed = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];

        return array_intersect(
            array_map('strtoupper', $route->methods()),
            $allowed
        );
    }
}
