<?php

namespace CoreFoundation\Console\ApiDocs;

/**
 * ScannedRoute
 *
 * Value object representing one discovered route.
 * Passed from RouteScanner → ReflectionReader → OperationBuilder.
 */
readonly class ScannedRoute
{
    public function __construct(
        public string $method,
        public string $uri,
        public string $action,   // e.g. "App\Http\Controllers\OrderController@store"
        public string $name,
        public array $middleware,
    ) {}

    public function controller(): string
    {
        return explode('@', $this->action)[0] ?? '';
    }

    public function controllerMethod(): string
    {
        return explode('@', $this->action)[1] ?? '';
    }
}
