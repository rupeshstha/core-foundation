<?php

use Illuminate\Support\Facades\Route;
use CoreFoundation\Http\Controllers\FeatureFlagController;

/*
|--------------------------------------------------------------------------
| Feature Flag Routes
|--------------------------------------------------------------------------
|
| These routes are registered automatically by CoreFoundationServiceProvider.
| The middleware stack is config-driven — CoreFoundation does not assume any
| particular authentication package. The default is Laravel's built-in 'auth'
| guard. Override in config/core-foundation.php:
|
|   'auth' => ['features_middleware' => ['auth:sanctum']]   // Sanctum
|   'auth' => ['features_middleware' => ['auth:api']]       // Passport
|   'auth' => ['features_middleware' => []]                 // unprotected (use resolveScope())
|
| Prefix: /features (no /api prefix — the host app applies its own prefix)
|
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
