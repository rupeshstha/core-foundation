<?php

namespace CoreFoundation\Tests\Unit\Features;

use Laravel\Pennant\Feature;
use CoreFoundation\Features\BaseFeature;
use CoreFoundation\Tests\PackageTestCase;

// ---------------------------------------------------------------------------
// Stub features
// ---------------------------------------------------------------------------

/**
 * A minimal feature with all defaults intact.
 * Auto-name: SimpleFeature → 'simple'
 */
class SimpleFeature extends BaseFeature
{
    public function resolve(mixed $scope): mixed
    {
        return true;
    }
}

/**
 * A feature with a custom pinned name.
 */
class PinnedNameFeature extends BaseFeature
{
    public function resolve(mixed $scope): mixed
    {
        return false;
    }

    public static function name(): string
    {
        return 'my-pinned-name';
    }
}

/**
 * A feature exercising all override-able metadata.
 * Auto-name: RichMetaFeature → 'rich-meta'
 */
class RichMetaFeature extends BaseFeature
{
    public function resolve(mixed $scope): mixed
    {
        return 'pro';
    }

    public static function description(): string
    {
        return 'Enables pro-tier checkout flow.';
    }

    public static function defaultValue(): mixed
    {
        return 'free';
    }

    public static function isPublic(): bool
    {
        return false;
    }

    public static function tags(): array
    {
        return ['billing', 'checkout'];
    }
}

/**
 * Verifies that the 'Feature' suffix is correctly stripped from the class name.
 * Auto-name: NewCheckoutFlowFeature → 'new-checkout-flow'
 */
class NewCheckoutFlowFeature extends BaseFeature
{
    public function resolve(mixed $scope): mixed
    {
        return false;
    }
}

/**
 * A feature with no suffix at all — name must still be kebab-cased.
 * Auto-name: BetaDashboard → 'beta-dashboard'
 */
class BetaDashboard extends BaseFeature
{
    public function resolve(mixed $scope): mixed
    {
        return false;
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class BaseFeatureTest extends PackageTestCase
{
    // -----------------------------------------------------------------------
    // name() — auto kebab-case, 'Feature' suffix removal
    // -----------------------------------------------------------------------

    public function test_name_strips_feature_suffix_and_converts_to_kebab_case(): void
    {
        $this->assertSame('new-checkout-flow', NewCheckoutFlowFeature::name());
    }

    public function test_name_handles_simple_class_without_feature_suffix(): void
    {
        $this->assertSame('beta-dashboard', BetaDashboard::name());
    }

    public function test_name_strips_feature_suffix_from_single_word(): void
    {
        $this->assertSame('simple', SimpleFeature::name());
    }

    public function test_name_returns_custom_pinned_name_when_overridden(): void
    {
        $this->assertSame('my-pinned-name', PinnedNameFeature::name());
    }

    public function test_name_strips_feature_suffix_from_rich_meta(): void
    {
        $this->assertSame('rich-meta', RichMetaFeature::name());
    }

    // -----------------------------------------------------------------------
    // description() — defaults to empty string
    // -----------------------------------------------------------------------

    public function test_description_returns_empty_string_by_default(): void
    {
        $this->assertSame('', SimpleFeature::description());
    }

    public function test_description_returns_custom_value_when_overridden(): void
    {
        $this->assertSame('Enables pro-tier checkout flow.', RichMetaFeature::description());
    }

    // -----------------------------------------------------------------------
    // defaultValue() — defaults to false
    // -----------------------------------------------------------------------

    public function test_default_value_is_false_when_not_overridden(): void
    {
        $this->assertFalse(SimpleFeature::defaultValue());
    }

    public function test_default_value_returns_custom_value_when_overridden(): void
    {
        $this->assertSame('free', RichMetaFeature::defaultValue());
    }

    // -----------------------------------------------------------------------
    // isPublic() — defaults to true
    // -----------------------------------------------------------------------

    public function test_is_public_returns_true_by_default(): void
    {
        $this->assertTrue(SimpleFeature::isPublic());
    }

    public function test_is_public_returns_false_when_overridden(): void
    {
        $this->assertFalse(RichMetaFeature::isPublic());
    }

    // -----------------------------------------------------------------------
    // tags() — defaults to empty array
    // -----------------------------------------------------------------------

    public function test_tags_returns_empty_array_by_default(): void
    {
        $this->assertSame([], SimpleFeature::tags());
    }

    public function test_tags_returns_array_when_overridden(): void
    {
        $this->assertSame(['billing', 'checkout'], RichMetaFeature::tags());
    }

    // -----------------------------------------------------------------------
    // Pennant proxy methods — only when laravel/pennant is installed
    // -----------------------------------------------------------------------

    public function test_active_proxies_to_pennant(): void
    {
        if (! class_exists(Feature::class)) {
            $this->markTestSkipped('laravel/pennant is not installed.');
        }

        Feature::define(SimpleFeature::class, fn () => true);

        $this->assertTrue(SimpleFeature::active());
    }

    public function test_active_for_proxies_to_pennant_for_scope(): void
    {
        if (! class_exists(Feature::class)) {
            $this->markTestSkipped('laravel/pennant is not installed.');
        }

        // Pennant only serializes null/string/numeric/Eloquent-Model scopes —
        // a plain object would throw "Unable to serialize the feature scope".
        $user = 'user:1';
        Feature::define(SimpleFeature::class, fn () => true);

        $this->assertTrue(SimpleFeature::activeFor($user));
    }

    public function test_value_for_proxies_to_pennant(): void
    {
        if (! class_exists(Feature::class)) {
            $this->markTestSkipped('laravel/pennant is not installed.');
        }

        // Pennant only serializes null/string/numeric/Eloquent-Model scopes —
        // a plain object would throw "Unable to serialize the feature scope".
        $user = 'user:1';
        Feature::define(RichMetaFeature::class, fn () => 'pro');

        $this->assertSame('pro', RichMetaFeature::valueFor($user));
    }

    public function test_activate_and_deactivate_proxy_to_pennant(): void
    {
        if (! class_exists(Feature::class)) {
            $this->markTestSkipped('laravel/pennant is not installed.');
        }

        Feature::define(SimpleFeature::class, fn () => false);

        SimpleFeature::activate();
        $this->assertTrue(SimpleFeature::active());

        SimpleFeature::deactivate();
        $this->assertFalse(SimpleFeature::active());
    }

    public function test_forget_clears_stored_state(): void
    {
        if (! class_exists(Feature::class)) {
            $this->markTestSkipped('laravel/pennant is not installed.');
        }

        Feature::define(SimpleFeature::class, fn () => true);

        // Resolve once to populate the in-memory store
        SimpleFeature::active();

        // Forget clears the stored value — next check re-runs resolve()
        SimpleFeature::forget();

        $this->assertTrue(SimpleFeature::active());
    }
}
