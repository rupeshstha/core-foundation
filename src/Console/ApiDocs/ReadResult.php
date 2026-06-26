<?php

namespace CoreFoundation\Console\ApiDocs;

use CoreFoundation\Attributes\ApiRequest;
use CoreFoundation\Attributes\ApiResponse;

/**
 * ReadResult
 *
 * Value object carrying all reflected metadata for one route.
 * Passed from ReflectionReader → OperationBuilder.
 */
readonly class ReadResult
{
    public function __construct(
        public ScannedRoute $route,
        public ?string $requestClass,
        public ?string $responseClass,
        public ?ApiRequest $requestMeta,
        public ?ApiResponse $responseMeta,
        public array $bodyParams,   // BodyParam[]
        public array $properties,   // [ propertyName => Property ]
    ) {}

    public function hasRequest(): bool
    {
        return $this->requestClass !== null;
    }

    public function hasResponse(): bool
    {
        return $this->responseClass !== null;
    }

    /**
     * Tags come from ApiRequest first, ApiResponse as fallback,
     * then derived from the URI segment as a last resort.
     */
    public function resolveTags(): array
    {
        if (! empty($this->requestMeta->tags)) {
            return $this->requestMeta->tags;
        }

        if (! empty($this->responseMeta->tags)) {
            return $this->responseMeta->tags;
        }

        // Derive from first meaningful URI segment e.g. /api/orders/... → ['Orders']
        $segments = explode('/', trim($this->route->uri, '/'));
        $segment = collect($segments)
            ->filter(fn ($s) => $s !== '' && $s !== 'api' && ! str_starts_with($s, '{'))
            ->first();

        return $segment ? [ucfirst($segment)] : ['General'];
    }

    public function resolveOperationId(): string
    {
        return $this->route->name
            ?: strtolower($this->route->method).'_'.str_replace(['/', '{', '}', '-'], ['_', '', '', '_'], trim($this->route->uri, '/'));
    }

    public function resolveDescription(): string
    {
        return $this->requestMeta->description
            ?? $this->responseMeta->description
            ?? '';
    }

    public function isDeprecated(): bool
    {
        return $this->requestMeta->deprecated ?? false;
    }

    public function responseStatus(): int
    {
        return $this->responseMeta->status ?? 200;
    }
}
