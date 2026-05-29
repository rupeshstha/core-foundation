<?php

namespace CoreFoundation\Tests\Unit\ServerTiming;

use Illuminate\Http\Request;
use Orchestra\Testbench\TestCase;
use Symfony\Component\Stopwatch\Stopwatch;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\Http\Middlewares\ServerTimingMiddleware;
use CoreFoundation\DevTools\ServerTiming\ServerTimingService;

class ServerTimingMiddlewareTest extends TestCase
{
    public function test_it_adds_server_timing_header(): void
    {
        $request = new Request;
        $timing = new ServerTimingService(new Stopwatch);

        $this->app->instance(ServerTimingService::class, $timing);

        $middleware = new ServerTimingMiddleware;

        $response = $middleware->handle($request, function ($req) {
            return new Response;
        });

        $this->assertArrayHasKey('server-timing', $response->headers->all());
    }

    public function test_it_adds_metrics_to_header(): void
    {
        $request = new Request;
        $timing = new ServerTimingService(new Stopwatch);
        $timing->record('db', 10.5);

        $this->app->instance(ServerTimingService::class, $timing);

        $middleware = new ServerTimingMiddleware;

        $response = $middleware->handle($request, function ($req) {
            return new Response;
        });

        $header = $response->headers->get('server-timing');
        $this->assertStringContainsString('db', $header);
        $this->assertStringContainsString('dur=10.5', $header);
    }
}
