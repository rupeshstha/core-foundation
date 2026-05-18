<?php

use CoreFoundation\Services\TestService;
use CoreFoundation\Services\InterceptTestService;

return [
    // /**
    //  * You can also restrict to intercept.
    //  *
    //  * If you wish to restrict to intercept method, You just need to specify method
    //  */
    // "restrict_interceptor_methods" => [
    //     TestService::class => [
    //         "index"
    //     ]
    // ],
    // "interceptors" =>
    [
        'interceptFrom' => TestService::class,
        'interceptTo' => InterceptTestService::class,
        'priority' => 3,
    ],
    [
        'interceptFrom' => TestService::class,
        'interceptTo' => InterceptTestService::class,
        'priority' => 9,
    ],
    [
        'interceptFrom' => TestService::class,
        'interceptTo' => InterceptTestService::class,
        'priority' => 2,
    ],
    [
        'interceptFrom' => TestService::class,
        'interceptTo' => InterceptTestService::class,
        'priority' => 0,
    ],
    [
        'interceptFrom' => TestService::class,
        'interceptTo' => InterceptTestService::class,
        'priority' => 3,
    ],
];
