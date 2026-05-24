<?php

use CoreFoundation\Repositories\Filter\Operators\InOperator;
use CoreFoundation\Repositories\Filter\Operators\OrOperator;
use CoreFoundation\Repositories\Filter\Operators\AndOperator;
use CoreFoundation\Repositories\Filter\Operators\LikeOperator;
use CoreFoundation\Repositories\Filter\Operators\AndOrOperator;
use CoreFoundation\Repositories\Filter\Operators\EqualOperator;
use CoreFoundation\Repositories\Filter\Operators\NotInOperator;
use CoreFoundation\Repositories\Filter\Operators\IsNullOperator;
use CoreFoundation\Repositories\Filter\Operators\NotLikeOperator;
use CoreFoundation\Repositories\Filter\Operators\LessThanOperator;
use CoreFoundation\Repositories\Filter\Operators\NotEqualOperator;
use CoreFoundation\Repositories\Filter\Operators\IsNotNullOperator;
use CoreFoundation\Repositories\Filter\Operators\GreaterThanOperator;
use CoreFoundation\Repositories\Filter\Operators\LessThanOrEqualOperator;
use CoreFoundation\Repositories\Filter\Operators\GreaterThanOrEqualOperator;

return [

    /*
    |--------------------------------------------------------------------------
    | Cache
    |--------------------------------------------------------------------------
    |
    | global      — Master switch for repository caching.
    |               Set to false to disable all repository cache globally.
    |
    | methods     — Repository methods whose results are cached by default.
    |               Concrete repositories can override via cachedMethods().
    |
    */

    'cache' => [
        'global' => env('REPOSITORY_CACHE_ENABLED', true),
        'methods' => ['fetchAll', 'fetchById'],
    ],

    /*
    |--------------------------------------------------------------------------
    | Filter Operators
    |--------------------------------------------------------------------------
    |
    | The operator registry maps identifier strings to operator class FQCNs.
    |
    | OVERRIDE a built-in operator — replace its class:
    |   '__like_' => App\Repositories\Filter\Operators\CustomLikeOperator::class,
    |
    | DISABLE a built-in operator — set its value to null:
    |   '__null_' => null,
    |
    | ADD a custom operator — add a new key/value pair:
    |   '__between_' => App\Repositories\Filter\Operators\BetweenOperator::class,
    |
    | All operator classes must implement:
    |   CoreFoundation\Repositories\Filter\Contracts\FilterOperator
    |
    */

    'operators' => [

        // ── Comparison ────────────────────────────────────────────────────────
        '__eq_' => EqualOperator::class,
        '__neq_' => NotEqualOperator::class,
        '__gt_' => GreaterThanOperator::class,
        '__gte_' => GreaterThanOrEqualOperator::class,
        '__lt_' => LessThanOperator::class,
        '__lte_' => LessThanOrEqualOperator::class,

        // ── String ────────────────────────────────────────────────────────────
        '__like_' => LikeOperator::class,
        '__nlike_' => NotLikeOperator::class,

        // ── Null ──────────────────────────────────────────────────────────────
        '__null_' => IsNullOperator::class,
        '__nnull_' => IsNotNullOperator::class,

        // ── Set ───────────────────────────────────────────────────────────────
        '__in_' => InOperator::class,
        '__nin_' => NotInOperator::class,

        // ── Logical grouping ──────────────────────────────────────────────────
        '__or_' => OrOperator::class,
        '__and_' => AndOperator::class,

        // ── Combined AND groups ORed together ─────────────────────────────────
        // '__andor_' => AndOrOperator::class,  // opt-in — not enabled by default
        // Enable by uncommenting or adding to your published config:
        //   FilterApplicator::addOperator(new AndOrOperator);

    ],

    /*
    |--------------------------------------------------------------------------
    | Pagination
    |--------------------------------------------------------------------------
    |
    | default_per_page — Records per page when no per_page param is provided.
    | max_per_page     — Hard cap. Prevents abuse via ?per_page=999999.
    |
    */

    'pagination' => [
        'default_per_page' => 25,
        'max_per_page' => 100,
    ],

];
