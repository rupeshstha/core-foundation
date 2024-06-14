<?php

use App\Models\Role;
use App\Models\User;

return [
    /**
     * Todo: make different config file but merge to same config key
     */
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
     *
     * Todo: make different config file but merge to same config key
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
     *
     * Todo: make different config file but merge to same config key
     */
    "search" => [
        "default" => env("CORE_SEARCH_ENGINE", "database"),
        "prefix" => env("CORE_INDEX_PREFIX", "core_foundation"),

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
        "engine_map" => [
            User::class => "database"
        ]
    ]
];
