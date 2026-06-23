<?php

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\Http\Middlewares\AttachCacheHeaders;
use CoreFoundation\Repositories\Cache\CacheBustCollector;

it('caps the number of busted tags', function () {
    $collector = new CacheBustCollector;

    $tags = array_map(fn ($i) => "tag-{$i}", range(1, 100));
    $collector->record($tags);

    expect($collector->all())->toHaveCount(50);
    expect($collector->isCapped())->toBeTrue();
});

it('appends full bust header when capped', function () {
    $collector = new CacheBustCollector;
    $collector->record(array_map(fn ($i) => "tag-{$i}", range(1, 60)));

    $middleware = new AttachCacheHeaders($collector);
    $request = Request::create('/', 'GET');

    $response = $middleware->handle($request, fn () => new Response('ok'));

    expect($response->headers->get('X-Cache-Full-Bust'))->toBe('true');
    expect(count(explode(',', $response->headers->get('X-Cache-Tags-Busted'))))->toBe(50);
});
