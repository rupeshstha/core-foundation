<?php

namespace CoreFoundation\Http\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\Repositories\Cache\CacheBustCollector;

/**
 * Appends X-Cache-Tags-Busted to any response where Redis cache was invalidated.
 *
 * The frontend SDK reads this header after mutations and calls
 * queryClient.invalidateQueries() for all registered matching query keys —
 * eliminating manual invalidation calls in the application layer.
 *
 * Registration (in your app's Http/Kernel.php or bootstrap/app.php):
 *
 *   $middleware->appendToGroup('api', AttachCacheHeaders::class);
 *
 * Header format:
 *   X-Cache-Tags-Busted: tenant:1:products:listing,tenant:1:products:record:42
 */
final class AttachCacheHeaders
{
    public function __construct(private readonly CacheBustCollector $collector) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if ($this->collector->hasAny()) {
            $response->headers->set(
                'X-Cache-Tags-Busted',
                implode(',', $this->collector->all()),
            );
        }

        if ($this->collector->isCapped()) {
            $response->headers->set('X-Cache-Full-Bust', 'true');
        }

        return $response;
    }
}
