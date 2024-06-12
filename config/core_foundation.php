<?php

use App\Models\Role;
use App\Models\User;

return [
	"repository" => [
        "pagination" => 25,
        "indexing" => [
            "cache" => [
                "driver" => "cache",
                "status" => true,
            ],
            "algolia" => [
                "driver" => "algolia",
                "status" => true,
            ]
        ],
	],
    /**
     * Global caching status
     */
    "cache" => [
        "global" => env("CORE_CACHE_GLOBAL", true),
        "repository" => env("CORE_CACHE_REPOSITORY", true),
        "cache_repository_methods" => [
            "fetchAll",
            "fetch"
        ],
        "cache_prefix" => env("APP_NAME"),
        "cache_ttl" => env("CORE_CACHE_TTL", 20),
    ],
    /**
     * Search engine
     */
    "search" => [
        "default" => "database",
        /*
        |--------------------------------------------------------------------------
        | Models for indexing
        |--------------------------------------------------------------------------
        |
        | The model listed here will be used to create/populate the indexes.
        | You can provide your own model here to run them all on the same
        | search engine.
        |
        */
        "models" => [
            User::class,
            Role::class,
        ],
        "prefix" => env("CORE_INDEX_PREFIX", "core_foundation"),
        "engine_map" => [
            User::class => "database"
        ]
    ]
];
