<?php

namespace CoreFoundation\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\Repositories\Cache\CacheReadCollector;

final class AttachReadTags
{
    public function __construct(
        private readonly CacheReadCollector $collector
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $this->collector->hasAny()) {
            return $response;
        }

        $headerName = (string) config('core-foundation.cdn.surrogate_key_header', 'Surrogate-Key');
        $separator  = (string) config('core-foundation.cdn.surrogate_key_separator', ' ');

        $response->headers->set(
            $headerName,
            implode($separator, $this->collector->all()),
        );

        if ($this->collector->isCapped()) {
            $response->headers->set('Surrogate-Key-Capped', 'true');
        }

        return $response;
    }
}
