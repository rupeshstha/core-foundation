<?php

namespace CoreFoundation\Http\Controllers;

use Throwable;
use Illuminate\Http\Request;
use Laravel\Pennant\Feature;
use Illuminate\Http\JsonResponse;
use CoreFoundation\Features\BaseFeature;

/**
 * FeatureFlagController
 *
 * Exposes feature flag states for the authenticated user via REST.
 *
 * Used by the Backoffice and Storefront frontends to conditionally
 * render UI, and by AI agents to check gating before calling guarded endpoints.
 *
 * Routes (registered by CoreFoundationServiceProvider):
 *   GET /features          → index()  — all flags for the authenticated user
 *   GET /features/{name}   → show()   — single flag by its string name
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ FEATURE KEY DESIGN — IMPORTANT                                               │
 * │                                                                             │
 * │ Pennant uses the feature's registered name as its key. For class-based       │
 * │ features, Pennant's default key is the fully-qualified class name            │
 * │ (e.g. "App\Features\NewCheckoutFlowFeature"). This is unsuitable for URLs.  │
 * │                                                                             │
 * │ RECOMMENDED: Register features with a clean string key:                     │
 * │                                                                             │
 * │   // In AppServiceProvider::boot():                                         │
 * │   Feature::define('new-checkout-flow', fn (User $user) =>                  │
 * │       $user->isOnPlan('pro')                                                │
 * │   );                                                                        │
 * │                                                                             │
 * │   // Or for class-based features, define an alias:                          │
 * │   Feature::define(                                                          │
 * │       NewCheckoutFlowFeature::name(),       // 'new-checkout-flow'          │
 * │       app(NewCheckoutFlowFeature::class)->resolve(...)                      │
 * │   );                                                                        │
 * │                                                                             │
 * │ With clean string keys, index() returns { "new-checkout-flow": true }       │
 * │ and show('new-checkout-flow') works without any encoding gymnastics.        │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ RESPONSE SHAPE                                                               │
 * │                                                                             │
 * │  GET /features                                                              │
 * │  {                                                                          │
 * │    "message": "Features fetched successfully.",                             │
 * │    "payload": {                                                             │
 * │      "features": {                                                          │
 * │        "new-checkout-flow": true,                                           │
 * │        "beta-dashboard": false                                              │
 * │      }                                                                      │
 * │    }                                                                        │
 * │  }                                                                          │
 * │                                                                             │
 * │  GET /features/new-checkout-flow                                            │
 * │  {                                                                          │
 * │    "message": "Feature fetched successfully.",                              │
 * │    "payload": {                                                             │
 * │      "name":        "new-checkout-flow",                                    │
 * │      "active":      true,                                                   │
 * │      "value":       true,                                                   │
 * │      "description": "Enables the redesigned checkout flow.",                │
 * │      "tags":        ["checkout"]                                            │
 * │    }                                                                        │
 * │  }                                                                          │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ SCOPE OVERRIDE                                                               │
 * │                                                                             │
 * │ The default scope is the authenticated user. Override resolveScope() in a   │
 * │ subclass to use a Tenant, Organisation, or composite scope instead.         │
 * │                                                                             │
 * │   class AppFeatureFlagController extends FeatureFlagController              │
 * │   {                                                                         │
 * │       protected function resolveScope(Request $request): mixed              │
 * │       {                                                                     │
 * │           return $request->user()?->tenant;                                 │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
class FeatureFlagController extends BaseController
{
    /**
     * Resolve the Pennant scope for all feature evaluations.
     *
     * Defaults to the authenticated user. Override in a subclass to use any
     * other scope — Tenant, Organisation, composite object, etc.
     */
    protected function resolveScope(Request $request): mixed
    {
        return $request->user();
    }

    /**
     * Return all public feature flag states for the resolved scope.
     *
     * Features that are BaseFeature subclasses and have isPublic() === false
     * are excluded from the response. Class-based feature keys are normalised
     * to their kebab-case name(). String-keyed features pass through as-is.
     *
     * Only features that have been loaded (via Feature::discover() or Feature::define())
     * appear in the response — undefined features are not included.
     */
    public function index(Request $request): JsonResponse
    {
        $scope = $this->resolveScope($request);

        try {
            $raw = Feature::for($scope)->all();
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }

        $features = [];

        foreach ($raw as $name => $value) {
            $isBaseFeature = class_exists($name) && is_a($name, BaseFeature::class, true);

            if ($isBaseFeature && ! $name::isPublic()) {
                continue;
            }

            $key = $isBaseFeature ? $name::name() : $name;
            $features[$key] = $value;
        }

        return $this->successResponse(
            message: $this->lang('core-foundation::features.fetch-all'),
            payload: ['features' => $features],
        );
    }

    /**
     * Return the state of a single feature flag for the resolved scope.
     *
     * The {feature} segment is the Pennant string key — the name passed to
     * Feature::define('name', ...). Use kebab-case for clean URLs (e.g. 'new-checkout-flow').
     *
     * Returns 404 when the feature key is not defined in Pennant, so callers
     * can distinguish "this flag is off" from "this flag doesn't exist".
     */
    public function show(Request $request, string $feature): JsonResponse
    {
        $scope = $this->resolveScope($request);

        try {
            $all = Feature::for($scope)->all();
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }

        if (! array_key_exists($feature, $all)) {
            return $this->errorResponse(
                message: $this->lang('core-foundation::features.not-found', ['feature' => $feature]),
                status: 404,
            );
        }

        $value = $all[$feature];
        $isBaseFeature = class_exists($feature) && is_a($feature, BaseFeature::class, true);

        $payload = [
            'name' => $isBaseFeature ? $feature::name() : $feature,
            'active' => (bool) $value,
            'value' => $value,
        ];

        if ($isBaseFeature) {
            $payload['description'] = $feature::description();
            $payload['tags'] = $feature::tags();
        }

        return $this->successResponse(
            message: $this->lang('core-foundation::features.fetch-one'),
            payload: $payload,
        );
    }
}
