<?php

use CoreFoundation\Notifications\JobFailedNotification;
use CoreFoundation\Notifications\JobStartedNotification;
use CoreFoundation\Notifications\JobCompletedNotification;

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    |
    | Middleware applied to the built-in feature flag routes (GET /features).
    | Override after publishing to match your application's auth guard:
    |
    |   'features_middleware' => ['auth:sanctum']   Sanctum
    |   'features_middleware' => ['auth:api']        Passport
    |   'features_middleware' => []                  unprotected
    |
    */

    'auth' => [
        'features_middleware' => [],
        'cookie_name' => env('AUTH_COOKIE_NAME', 'dubdubco_token'),
        'cookie_ttl' => env('AUTH_COOKIE_TTL', 1440), // 1 day
        'cookie_domain' => env('AUTH_COOKIE_DOMAIN'), // .dubdubco.com for cross-subdomain
        'remove_token_from_body' => env('AUTH_REMOVE_TOKEN_FROM_BODY', false),
    ],

    'tenancy' => [
        'header' => 'X-Tenant',
        'require_trusted_source' => env('TENANCY_REQUIRE_TRUSTED_SOURCE', false),
        'trusted_sources' => explode(',', env('TENANCY_TRUSTED_SOURCES', '127.0.0.1')),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | global  — Master on/off switch for all CoreFoundation caching.
    | prefix  — Prefix applied to all cache keys (defaults to APP_NAME).
    | ttl     — Default TTL in seconds for cached items.
    |
    | Repository-level caching is configured in config/repository.php.
    |
    */

    'cache' => [
        'global'        => env('CORE_CACHE_GLOBAL', true),
        'cache_prefix'  => env('APP_NAME'),
        'cache_ttl'     => env('CORE_CACHE_TTL', 20),
    ],

    /*
    |--------------------------------------------------------------------------
    | Notifications
    |--------------------------------------------------------------------------
    |
    | Notification classes dispatched by BaseJob on failure, start, and
    | completion. Set any value to null to disable that notification type.
    |
    */

    'notifications' => [
        'channel' => env('CORE_NOTIFY_CHANNEL', 'slack'),
        'jobs' => [
            'failed'    => JobFailedNotification::class,
            'started'   => JobStartedNotification::class,
            'completed' => JobCompletedNotification::class,
            'notifiables' => [
                'channel' => env('CORE_NOTIFY_CHANNEL', 'slack'),
                'route'   => env('CORE_NOTIFY_ROUTE'),
            ],
        ],
    ],

];
