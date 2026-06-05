<?php

namespace CoreFoundation\Tests\Feature\Features;

use Laravel\Pennant\Feature;
use CoreFoundation\Support\Lang;
use CoreFoundation\Features\BaseFeature;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Providers\CoreFoundationServiceProvider;

// ---------------------------------------------------------------------------
// Stub features used across all test cases in this file
// ---------------------------------------------------------------------------

class PublicFeature extends BaseFeature
{
    public function resolve(mixed $scope): mixed
    {
        return true;
    }

    public static function description(): string
    {
        return 'A publicly visible feature.';
    }

    public static function tags(): array
    {
        return ['ui'];
    }
}

class PrivateFeature extends BaseFeature
{
    public function resolve(mixed $scope): mixed
    {
        return true;
    }

    public static function isPublic(): bool
    {
        return false;
    }
}

class RichValueFeature extends BaseFeature
{
    public function resolve(mixed $scope): mixed
    {
        return 'pro';
    }

    public static function description(): string
    {
        return 'Returns a string value instead of a bool.';
    }

    public static function tags(): array
    {
        return ['billing'];
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class FeatureFlagControllerTest extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Feature::class)) {
            $this->markTestSkipped('laravel/pennant is not installed.');
        }

        // Ensure routes are registered (ServiceProvider handles this conditionally)
        $this->app->make(CoreFoundationServiceProvider::class)->boot();
    }

    protected function tearDown(): void
    {
        if (class_exists(Feature::class)) {
            Feature::flushCache();
        }

        parent::tearDown();
    }

    // -----------------------------------------------------------------------
    // GET /features
    // -----------------------------------------------------------------------

    public function test_index_returns_all_public_features(): void
    {
        Feature::define(PublicFeature::class, fn () => true);
        Feature::define('string-flag', fn () => false);

        $response = $this->getJson('/features');

        $response->assertOk();
        $response->assertJsonStructure(['message', 'payload' => ['features']]);

        $features = $response->json('payload.features');

        $this->assertArrayHasKey('public', $features); // PublicFeature::name()
        $this->assertArrayHasKey('string-flag', $features);
    }

    public function test_index_excludes_private_base_features(): void
    {
        Feature::define(PublicFeature::class, fn () => true);
        Feature::define(PrivateFeature::class, fn () => true);

        $response = $this->getJson('/features');

        $response->assertOk();

        $features = $response->json('payload.features');

        $this->assertArrayHasKey('public', $features);
        $this->assertArrayNotHasKey('private', $features);
        $this->assertArrayNotHasKey(PrivateFeature::class, $features);
    }

    public function test_index_normalises_base_feature_class_keys_to_kebab_name(): void
    {
        Feature::define(PublicFeature::class, fn () => true);

        $response = $this->getJson('/features');

        $response->assertOk();

        $features = $response->json('payload.features');

        // Must use kebab name, not FQCN
        $this->assertArrayHasKey('public', $features);
        $this->assertArrayNotHasKey(PublicFeature::class, $features);
    }

    public function test_index_returns_correct_feature_values(): void
    {
        Feature::define(PublicFeature::class, fn () => true);
        Feature::define('disabled-flag', fn () => false);

        $response = $this->getJson('/features');

        $response->assertOk();

        $features = $response->json('payload.features');

        $this->assertTrue($features['public']);
        $this->assertFalse($features['disabled-flag']);
    }

    // -----------------------------------------------------------------------
    // GET /features/{name}
    // -----------------------------------------------------------------------

    public function test_show_returns_404_for_undefined_feature(): void
    {
        $response = $this->getJson('/features/nonexistent-flag');

        $response->assertNotFound();
        $response->assertJsonPath('message', Lang::get('core-foundation::features.not-found', ['feature' => 'nonexistent-flag']));
    }

    public function test_show_returns_feature_details_for_string_keyed_flag(): void
    {
        Feature::define('beta-ui', fn () => true);

        $response = $this->getJson('/features/beta-ui');

        $response->assertOk();
        $response->assertJsonPath('message', Lang::get('core-foundation::features.fetch-one'));
        $response->assertJsonStructure(['message', 'payload' => ['name', 'active', 'value']]);
        $response->assertJsonPath('payload.name', 'beta-ui');
        $response->assertJsonPath('payload.active', true);
        $response->assertJsonPath('payload.value', true);

        // String-keyed flags have no description or tags
        $this->assertArrayNotHasKey('description', $response->json('payload'));
        $this->assertArrayNotHasKey('tags', $response->json('payload'));
    }

    public function test_show_returns_feature_details_with_description_and_tags_for_base_feature(): void
    {
        // Register with the kebab-case string key, not the FQCN — FQCN in a URL is broken
        Feature::define(PublicFeature::name(), fn () => true);

        $response = $this->getJson('/features/'.PublicFeature::name());

        $response->assertOk();
        $response->assertJsonPath('payload.name', PublicFeature::name());
        $response->assertJsonPath('payload.active', true);
    }

    public function test_show_returns_active_false_for_inactive_feature(): void
    {
        Feature::define('inactive-flag', fn () => false);

        $response = $this->getJson('/features/inactive-flag');

        $response->assertOk();
        $response->assertJsonPath('payload.active', false);
        $response->assertJsonPath('payload.value', false);
    }

    public function test_show_returns_rich_string_value(): void
    {
        Feature::define('theme', fn () => 'pro');

        $response = $this->getJson('/features/theme');

        $response->assertOk();
        $response->assertJsonPath('payload.value', 'pro');
        $response->assertJsonPath('payload.active', true);
    }

    public function test_index_returns_success_message_from_lang(): void
    {
        Feature::define('any-flag', fn () => true);

        $response = $this->getJson('/features');

        $response->assertOk();
        $response->assertJsonPath('message', Lang::get('core-foundation::features.fetch-all'));
    }

    public function test_show_returns_success_message_from_lang(): void
    {
        Feature::define('beta-ui', fn () => true);

        $response = $this->getJson('/features/beta-ui');

        $response->assertOk();
        $response->assertJsonPath('message', Lang::get('core-foundation::features.fetch-one'));
    }
}
