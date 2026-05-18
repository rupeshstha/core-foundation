<?php

use Illuminate\Support\Facades\Notification;
use CoreFoundation\Notifications\JobFailedNotification;
use CoreFoundation\Notifications\JobStartedNotification;
use CoreFoundation\Notifications\JobCompletedNotification;

return [
    /**
     * Todo: make different config file but merge to same config key
     */
    'repository' => [
        'pagination' => 25,
        'indexing' => [
            'cache' => [
                'driver' => 'cache',
                'status' => true,
            ],
            'algolia' => [
                'driver' => 'algolia',
                'status' => true,
            ],
        ],
    ],
    /**
     * Global caching status
     *
     * Todo: make different config file but merge to same config key
     */
    'cache' => [
        'global' => env('CORE_CACHE_GLOBAL', true),
        'repository' => env('CORE_CACHE_REPOSITORY', true),
        'cache_repository_methods' => [
            'fetchAll',
            'fetch',
        ],
        'cache_prefix' => env('APP_NAME'),
        'cache_ttl' => env('CORE_CACHE_TTL', 20),
    ],

    /**
     * Notifications
     */
    'notifications' => [
        'channel' => env('CORE_NOTIFY_CHANNEL', 'slack'),
        /**
         * Before changing notification channel, Change jobs channel dependencies.
         */
        'jobs' => [
            'failed' => JobFailedNotification::class,
            'started' => JobStartedNotification::class,
            'completed' => JobCompletedNotification::class,
            'notifiables' => [
                'channel' => env('CORE_NOTIFY_CHANNEL', 'slack'),
                'route' => env('CORE_NOTIFY_ROUTE', config('services.slack.webhook')),
            ],
        ],
    ],
];
