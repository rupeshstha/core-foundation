<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Enable Server-Timing Headers
    |--------------------------------------------------------------------------
    |
    | Master switch for the Server-Timing feature.
    | Set to false to disable entirely regardless of environment.
    |
    | You can also control this per-deploy via environment variable:
    |   SERVER_TIMING_ENABLED=false
    |
    */

    'enabled' => env('SERVER_TIMING_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Allowed Environments
    |--------------------------------------------------------------------------
    |
    | Server-Timing headers are only sent when the application is running
    | in one of these environments. An empty array means all environments.
    |
    | To send in all environments:
    |   'environments' => []
    |
    | To send only in local and staging:
    |   'environments' => ['local', 'staging']
    |
    | Note: sending timing data in production exposes internal architecture
    | to end users and potential attackers. Consider whether this is acceptable
    | for your threat model before enabling in production.
    |
    */

    'environments' => array_filter(
        explode(',', env('SERVER_TIMING_ENVIRONMENTS', 'local,staging')),
        fn ($env) => ! empty(trim($env)),
    ),

    /*
    |--------------------------------------------------------------------------
    | Header Name
    |--------------------------------------------------------------------------
    |
    | The HTTP response header name. The W3C standard is "Server-Timing".
    | Change this only if your infrastructure requires a different name.
    |
    */

    'header' => env('SERVER_TIMING_HEADER', 'Server-Timing'),

    /*
    |--------------------------------------------------------------------------
    | Auto-Instrumentation
    |--------------------------------------------------------------------------
    |
    | Controls which CoreFoundation layers are automatically measured
    | when the corresponding opt-in traits are used.
    |
    | These settings do not enable the traits — they control whether the
    | traits produce measurements when they are used.
    |
    | Set a value to false to silence a layer's measurements without
    | removing the trait from your service class.
    |
    */

    'instrument' => [

        // Measure BaseService::throughPipes() calls (via MeasuresPerformance trait)
        'pipelines' => env('SERVER_TIMING_INSTRUMENT_PIPELINES', true),

        // Measure HasCacheable calls (via MeasuresCachePerformance trait)
        'cache' => env('SERVER_TIMING_INSTRUMENT_CACHE', true),

    ],

];
