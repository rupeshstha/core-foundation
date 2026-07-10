<?php

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\Http\Middlewares\AttachReadTags;
use CoreFoundation\Repositories\Cache\CacheReadCollector;

it('emits surrogate key header with space-separated tags', function () {
    $collector = new CacheReadCollector;
    $collector->record(['tenant:1:products:listing', 'tenant:1:products:record:42']);

    $middleware = new AttachReadTags($collector);
    $response = $middleware->handle(Request::create('/'), fn () => new Response('ok'));

    expect($response->headers->get('Surrogate-Key'))
        ->toBe('tenant:1:products:listing tenant:1:products:record:42');
});

it('does not emit a header when no tags were recorded', function () {
    $collector = new CacheReadCollector;

    $middleware = new AttachReadTags($collector);
    $response = $middleware->handle(Request::create('/'), fn () => new Response('ok'));

    expect($response->headers->has('Surrogate-Key'))->toBeFalse();
});

it('emits surrogate key capped header when cap is exceeded', function () {
    $collector = new CacheReadCollector;
    $collector->record(array_map(fn ($i) => "tag-{$i}", range(1, 60)));

    $middleware = new AttachReadTags($collector);
    $response = $middleware->handle(Request::create('/'), fn () => new Response('ok'));

    expect($response->headers->get('Surrogate-Key-Capped'))->toBe('true');
});

it('does not emit capped header when under the cap', function () {
    $collector = new CacheReadCollector;
    $collector->record(['tag-a', 'tag-b']);

    $middleware = new AttachReadTags($collector);
    $response = $middleware->handle(Request::create('/'), fn () => new Response('ok'));

    expect($response->headers->has('Surrogate-Key-Capped'))->toBeFalse();
});

it('uses the configured header name', function () {
    config(['core-foundation.cdn.surrogate_key_header' => 'Cache-Tag']);

    $collector = new CacheReadCollector;
    $collector->record(['tenant:1:products:listing']);

    $middleware = new AttachReadTags($collector);
    $response = $middleware->handle(Request::create('/'), fn () => new Response('ok'));

    expect($response->headers->has('Cache-Tag'))->toBeTrue();
    expect($response->headers->has('Surrogate-Key'))->toBeFalse();
});

it('uses the configured separator', function () {
    config(['core-foundation.cdn.surrogate_key_separator' => ',']);

    $collector = new CacheReadCollector;
    $collector->record(['tenant:1:products:listing', 'tenant:1:products:record:1']);

    $middleware = new AttachReadTags($collector);
    $response = $middleware->handle(Request::create('/'), fn () => new Response('ok'));

    expect($response->headers->get('Surrogate-Key'))
        ->toBe('tenant:1:products:listing,tenant:1:products:record:1');
});
