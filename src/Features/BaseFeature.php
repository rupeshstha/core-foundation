<?php

namespace CoreFoundation\Features;

use Illuminate\Support\Str;
use Laravel\Pennant\Feature;

/**
 * BaseFeature
 *
 * Abstract base class for all Pennant class-based feature flags.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ HOW PENNANT CLASS-BASED FEATURES WORK                                       │
 * │                                                                             │
 * │ Pennant calls resolve() automatically when checking feature state.          │
 * │ You never call resolve() directly — Pennant does, with the current scope.   │
 * │                                                                             │
 * │ The result is stored (DB or array driver) so resolve() only runs once       │
 * │ per scope per request. Use forget() to clear the stored state.             │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE PATTERN                                                               │
 * │                                                                             │
 * │   final class NewCheckoutFlowFeature extends BaseFeature                    │
 * │   {                                                                         │
 * │       public function resolve(mixed $scope): mixed                          │
 * │       {                                                                     │
 * │           // $scope is the authenticated user (or whatever was set)         │
 * │           return $scope?->isOnPlan('pro') ?? false;                         │
 * │       }                                                                     │
 * │   }                                                                         │
 * │                                                                             │
 * │   // In a service or controller:                                            │
 * │   NewCheckoutFlowFeature::active();              // default scope           │
 * │   NewCheckoutFlowFeature::activeFor($user);      // specific user           │
 * │   NewCheckoutFlowFeature::valueFor($user);       // for rich flags          │
 * │                                                                             │
 * │   // In Blade (via Pennant):                                                │
 * │   @feature('App\Features\NewCheckoutFlowFeature')                           │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ AGENTIC LENS                                                                │
 * │                                                                             │
 * │ Feature flags are first-class agents. An AI agent can:                      │
 * │   - Query which features are active for a merchant scope                    │
 * │   - Conditionally branch workflows based on feature state                   │
 * │   - Activate/deactivate features as part of a merchant onboarding flow      │
 * │                                                                             │
 * │ The FeatureFlagController exposes GET /features and GET /features/{feature} │
 * │ — both return deterministic, agent-parseable shapes.                        │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
abstract class BaseFeature
{
    /**
     * Determine the feature's value for the given scope.
     *
     * Pennant calls this automatically when checking feature state.
     * Never call this method directly.
     *
     * Return a bool for simple on/off flags.
     * Return any scalar or array for rich (multi-value) flags.
     *
     * CONTRACT: resolve() must never throw. Catch domain exceptions internally
     * and return the appropriate fallback (usually false or defaultValue()).
     */
    abstract public function resolve(mixed $scope): mixed;

    // =========================================================================
    // Metadata — override in concrete features for developer tooling,
    // dashboards, and API introspection.
    // =========================================================================

    /**
     * Canonical API name for this feature flag (kebab-case, no 'Feature' suffix).
     *
     * Used by FeatureFlagController to key the response payload.
     * Override to pin a stable name independent of future class renames.
     *
     * Examples:
     *   NewCheckoutFlowFeature → 'new-checkout-flow'
     *   BetaDashboardFeature   → 'beta-dashboard'
     *   PayPerUseFeature       → 'pay-per-use'
     */
    public static function name(): string
    {
        return Str::kebab(
            Str::replaceLast('Feature', '', class_basename(static::class))
        );
    }

    /**
     * Human-readable description of what this feature flag controls.
     *
     * Used by FeatureFlagController's index response, admin dashboards,
     * and agent introspection. Override with a meaningful sentence.
     *
     * Example:
     *   public static function description(): string
     *   {
     *       return 'Enables the redesigned checkout flow with one-click payment.';
     *   }
     */
    public static function description(): string
    {
        return '';
    }

    /**
     * The value returned when the flag has never been resolved for a scope.
     *
     * Used by FeatureFlagController::show() to distinguish "undefined" from
     * "resolved to false". Override for rich (non-bool) flags to declare
     * the expected default shape.
     */
    public static function defaultValue(): mixed
    {
        return false;
    }

    /**
     * Whether this flag should be exposed through the public REST API.
     *
     * Return false for internal/infrastructure flags that should never be
     * visible to frontends or external agents. FeatureFlagController::index()
     * excludes non-public features from its response.
     *
     * Example:
     *   public static function isPublic(): bool { return false; }
     */
    public static function isPublic(): bool
    {
        return true;
    }

    /**
     * Organisational tags for grouping flags in dashboards and tooling.
     *
     * Example:
     *   public static function tags(): array { return ['billing', 'checkout']; }
     */
    public static function tags(): array
    {
        return [];
    }

    // =========================================================================
    // Pennant proxy methods — thin wrappers so consumers never import Feature
    // =========================================================================

    /**
     * Check if this feature is active for the default (global) scope.
     */
    public static function active(): bool
    {
        return Feature::active(static::class);
    }

    /**
     * Check if this feature is active for a specific scope (e.g., a User or Tenant).
     */
    public static function activeFor(mixed $scope): bool
    {
        return Feature::for($scope)->active(static::class);
    }

    /**
     * Get the resolved value for a specific scope.
     * For bool features this matches active(). For rich features this returns the value.
     */
    public static function valueFor(mixed $scope): mixed
    {
        return Feature::for($scope)->value(static::class);
    }

    /**
     * Activate this feature for a scope, or globally when scope is null.
     */
    public static function activate(mixed $scope = null): void
    {
        match (true) {
            $scope !== null => Feature::for($scope)->activate(static::class),
            default => Feature::activate(static::class),
        };
    }

    /**
     * Deactivate this feature for a scope, or globally when scope is null.
     */
    public static function deactivate(mixed $scope = null): void
    {
        match (true) {
            $scope !== null => Feature::for($scope)->deactivate(static::class),
            default => Feature::deactivate(static::class),
        };
    }

    /**
     * Forget the stored state so resolve() runs fresh on the next check.
     * Pass a scope to clear only that scope; omit to clear all stored values.
     */
    public static function forget(mixed $scope = null): void
    {
        match (true) {
            $scope !== null => Feature::for($scope)->forget(static::class),
            default => Feature::forget(static::class),
        };
    }
}
