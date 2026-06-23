<?php

use Illuminate\Support\Facades\Route;
use CoreFoundation\Http\Controllers\FeatureFlagController;
use CoreFoundation\Http\Middlewares\AuthenticateWithCookie;
use CoreFoundation\Http\Controllers\BroadcastAuthController;

/*
|--------------------------------------------------------------------------
| Core Foundation Routes
|--------------------------------------------------------------------------
*/

Route::middleware(config('core-foundation.auth.features_middleware', ['auth']))
    ->prefix('features')
    ->group(static function (): void {
        Route::get('/', [FeatureFlagController::class, 'index'])
            ->name('core.features.index');

        Route::get('/{feature}', [FeatureFlagController::class, 'show'])
            ->name('core.features.show')
            ->where('feature', '.+'); // allow forward slashes and backslashes (URL-encoded)
    });

/**
 * WebSocket Broadcasting Authorization
 *
 * This route is used by the frontend SDK to authorize private channels
 * when using httpOnly cookies (where JS cannot read the token).
 */
Route::post('/broadcasting/auth', BroadcastAuthController::class)
    ->middleware(['api', AuthenticateWithCookie::class])
    ->name('core.broadcasting.auth');
