<?php

use CoreFoundation\Http\Middlewares\SetAuthCookie;
use CoreFoundation\Http\Middlewares\AuthenticateWithCookie;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Cookie;

it('attaches a cookie when a response contains an access token', function () {
    Config::set('core-foundation.auth.cookie_name', 'test_token');
    
    $request = Request::create('/login', 'POST');
    $middleware = new SetAuthCookie();
    
    $response = new JsonResponse(['access_token' => 'secret-token']);
    
    $result = $middleware->handle($request, fn() => $response);
    
    $cookies = $result->headers->getCookies();
    expect($cookies)->toHaveCount(1);
    expect($cookies[0]->getName())->toBe('test_token');
    expect($cookies[0]->getValue())->toBe('secret-token');
    expect($cookies[0]->isHttpOnly())->toBeTrue();
});

it('injects bearer token from cookie into authorization header', function () {
    Config::set('core-foundation.auth.cookie_name', 'test_token');
    
    $request = Request::create('/user', 'GET');
    $request->cookies->set('test_token', 'cookie-secret');
    
    $middleware = new AuthenticateWithCookie();
    
    $middleware->handle($request, function ($req) {
        expect($req->headers->get('Authorization'))->toBe('Bearer cookie-secret');
        return response('ok');
    });
});

it('does not overwrite existing authorization header', function () {
    Config::set('core-foundation.auth.cookie_name', 'test_token');
    
    $request = Request::create('/user', 'GET');
    $request->headers->set('Authorization', 'Bearer existing-token');
    $request->cookies->set('test_token', 'cookie-secret');
    
    $middleware = new AuthenticateWithCookie();
    
    $middleware->handle($request, function ($req) {
        expect($req->headers->get('Authorization'))->toBe('Bearer existing-token');
        return response('ok');
    });
});
