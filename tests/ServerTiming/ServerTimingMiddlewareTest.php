<?php

namespace CoreFoundation\Tests\ServerTiming;

use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\Http\Middlewares\ServerTimingMiddleware;
use CoreFoundation\Facades\Services\ServerTimingFacadeService;

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
