<?php

namespace CoreFoundation\Tests\ServerTiming;

use CoreFoundation\Facades\Services\ServerTimingFacadeService;
use CoreFoundation\Http\Middlewares\ServerTimingMiddleware;
use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Stopwatch\Stopwatch;

class ServerTimingMiddlewareTest extends TestCase
{
    /** @test */
    public function it_add_server_timing_header(): void
    {
        $request = new Request;

        $timing = new ServerTimingFacadeService(new Stopwatch);

        $middleware = new ServerTimingMiddleware($timing);

        $response = $middleware->handle($request, function ($req) {
            return new Response;
        });

        $this->assertArrayHasKey('server-timing', $response->headers->all());
    }
}
