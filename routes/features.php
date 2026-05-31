<?php

use Illuminate\Support\Facades\Route;
use CoreFoundation\Http\Controllers\FeatureFlagController;

/*
|--------------------------------------------------------------------------
| Feature Flag Routes
|--------------------------------------------------------------------------
|
| These routes are registered automatically by CoreFoundationServiceProvider.
| They require Sanctum authentication — the authenticated user is used as
| the Pennant scope for all feature evaluations.
|
| Prefix: /features (no /api prefix — the host app applies its own prefix)
|
*/

Route::middleware('auth:sanctum')
    ->prefix('features')
    ->group(static function (): void {
        Route::get('/', [FeatureFlagController::class, 'index'])
            ->name('core.features.index');

        Route::get('/{feature}', [FeatureFlagController::class, 'show'])
            ->name('core.features.show')
            ->where('feature', '.+'); // allow forward slashes and backslashes (URL-encoded)
    });
