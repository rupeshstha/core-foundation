<?php

use Illuminate\Http\Request;
use CoreFoundation\Exceptions\MaintenanceModeException;

it('renders a 503 response with metadata', function () {
    $exception = new MaintenanceModeException('Down for tests', 120, 'System Upgrade');

    $request = Request::create('/', 'GET');
    $request->headers->set('Accept', 'application/json');

    $response = $exception->render($request);

    expect($response->getStatusCode())->toBe(503);
    expect($response->headers->get('Retry-After'))->toBe('120');

    $data = $response->getData(true);
    expect($data['message'])->toBe('Down for tests');
    expect($data['meta']['retry_after'])->toBe(120);
    expect($data['meta']['reason'])->toBe('System Upgrade');
});
