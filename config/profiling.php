<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Master Switch
    |--------------------------------------------------------------------------
    |
    | Disabling this skips ALL profiling regardless of other flags.
    | No overhead — ProfilingMiddleware returns next($request) immediately.
    |
    */

    'enabled' => env('PROFILING_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Environments
    |--------------------------------------------------------------------------
    |
    | Profiling only runs in these environments.
    | Empty array = all environments (only do this if you know what you're doing).
    |
    | Methods marked #[Profile(inProduction: true)] bypass this restriction.
    |
    */

    'environments' => array_filter(
        explode(',', env('PROFILING_ENVIRONMENTS', 'local,staging')),
        fn ($e) => ! empty(trim($e)),
    ),

    /*
    |--------------------------------------------------------------------------
    | Layer Flags
    |--------------------------------------------------------------------------
    |
    | Enable profiling per architectural layer.
    | Each flag controls whether #[Profile] methods in that layer are measured.
    |
    | Enabling a layer has zero overhead on methods NOT marked #[Profile].
    | Overhead only occurs when a #[Profile]-marked method is called.
    |
    */

    'layers' => [

        // BaseService subclasses — measures throughPipes() and #[Profile] methods
        'services' => env('PROFILING_SERVICES', true),

        // BaseRepository subclasses — measures fetchAll, fetchById, #[Profile] methods
        'repositories' => env('PROFILING_REPOSITORIES', true),

        // BaseController — measures full controller action dispatch time
        'controllers' => env('PROFILING_CONTROLLERS', true),

        // HasCacheable — measures cache hit/miss per tagged key
        'cache' => env('PROFILING_CACHE', true),

        // DatabasePerformanceListener — query count + total duration
        'database' => env('PROFILING_DATABASE', true),

    ],

    /*
    |--------------------------------------------------------------------------
    | Thresholds — Slow Method Warnings
    |--------------------------------------------------------------------------
    |
    | When a #[Profile]-marked method exceeds these thresholds (in ms),
    | a warning metric is added to the Server-Timing header:
    |   "slow-{metric};desc="Slow: {label}";dur=X"
    |
    | This turns the DevTools bar red — an immediate visual signal.
    | Set to null to disable threshold warnings for that layer.
    |
    */

    'thresholds' => [
        'service' => env('PROFILING_THRESHOLD_SERVICE', 100),    // ms
        'repository' => env('PROFILING_THRESHOLD_REPOSITORY', 50),  // ms
        'controller' => env('PROFILING_THRESHOLD_CONTROLLER', 500), // ms
    ],

    /*
    |--------------------------------------------------------------------------
    | Code Quality — Static Analysis Integration
    |--------------------------------------------------------------------------
    |
    | Recommended thresholds for php-smelly-code-detector when used with
    | the CoreFoundation architecture. Run: php artisan core:analyse
    |
    | These are read by the core:analyse Artisan command to configure
    | the external tool. They do not affect runtime behaviour.
    |
    | Smell score = weighted sum of: lines, arguments, cyclomatic complexity
    |
    */

    'quality' => [

        // Maximum smell score before a method is flagged (lower = stricter)
        'smell_threshold' => env('PROFILING_SMELL_THRESHOLD', 30),

        // Maximum cyclomatic complexity per method
        'max_complexity' => env('PROFILING_MAX_COMPLEXITY', 10),

        // Maximum lines of code per method
        'max_loc' => env('PROFILING_MAX_LOC', 30),

        // Maximum method arguments
        'max_args' => env('PROFILING_MAX_ARGS', 4),

        // Directories to analyse (relative to base_path())
        'paths' => ['src', 'app'],

        // Directories to exclude
        'exclude' => ['vendor', 'tests', 'database'],

    ],

];
