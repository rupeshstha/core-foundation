<?php

return [

    /*
    |--------------------------------------------------------------------------
    | API Information
    |--------------------------------------------------------------------------
    |
    | Top-level metadata written into the OpenAPI "info" object.
    |
    */

    'title' => env('API_DOCS_TITLE', config('app.name').' API'),
    'description' => env('API_DOCS_DESCRIPTION', ''),
    'version' => env('API_DOCS_VERSION', '1.0.0'),

    'contact' => [
        'name' => env('API_DOCS_CONTACT_NAME'),
        'email' => env('API_DOCS_CONTACT_EMAIL'),
        'url' => env('API_DOCS_CONTACT_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Servers
    |--------------------------------------------------------------------------
    |
    | Listed in the OpenAPI "servers" array. Defaults to APP_URL if empty.
    |
    */

    'servers' => [
        [
            'url' => env('APP_URL', 'http://localhost'),
            'description' => 'Current environment',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Route Discovery
    |--------------------------------------------------------------------------
    |
    | prefix  — only routes starting with this URI segment are scanned.
    | exclude — route names to skip (useful for internal/webhook endpoints).
    |
    */

    'prefix' => 'api',

    'exclude' => [
        // 'api.internal.health',
        // 'api.webhooks.stripe',
    ],

    /*
    |--------------------------------------------------------------------------
    | Manual Route Supplements
    |--------------------------------------------------------------------------
    |
    | Routes that cannot be auto-discovered (closures, third-party packages)
    | can be documented manually here. These are merged with auto-scanned routes.
    |
    | Each entry must have: method, uri, action (Controller@method).
    | Optional: name, middleware.
    |
    */

    'routes' => [
        // [
        //     'method' => 'GET',
        //     'uri'    => '/api/health',
        //     'action' => 'App\Http\Controllers\HealthController@index',
        //     'name'   => 'api.health',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Output
    |--------------------------------------------------------------------------
    |
    | output — directory where openapi.yaml and openapi.json are written.
    |          Overridable per-run with --output option.
    |
    | format — 'yaml' | 'json' | 'both'.
    |          Overridable per-run with --format option.
    |
    */
    'output' => storage_path('app/api-docs'),
    'format' => 'both',
];
