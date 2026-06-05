<?php

namespace CoreFoundation\Tests\Unit\Support;

use ReflectionClass;
use CoreFoundation\Support\Lang;
use CoreFoundation\Traits\HasLang;
use CoreFoundation\Tests\PackageTestCase;

// ---------------------------------------------------------------------------
// Controller stub for HasLang instance method tests
// ---------------------------------------------------------------------------

class ProductController
{
    use HasLang;

    public function getMessage(string $key, array $replace = []): string
    {
        return $this->lang($key, $replace);
    }

    public function getPrefix(): string
    {
        return $this->langPrefix();
    }
}

class CustomPrefixController
{
    use HasLang;

    protected function langPrefix(): string
    {
        return 'orders';
    }

    public function getMessage(string $key): string
    {
        return $this->lang($key);
    }
}

// ---------------------------------------------------------------------------
// Tests
// ---------------------------------------------------------------------------

class LangTest extends PackageTestCase
{
    // =========================================================================
    // Lang::get() — static resolver, no prefix
    // =========================================================================

    public function test_lang_get_resolves_registered_package_key(): void
    {
        // Package translations are loaded by CoreFoundationServiceProvider
        $result = Lang::get('core-foundation::http.unauthenticated');

        $this->assertEquals('Unauthenticated.', $result);
    }

    public function test_lang_get_resolves_all_http_keys(): void
    {
        $keys = [
            'unauthenticated'       => 'Unauthenticated.',
            'unauthorized'          => 'This action is unauthorized.',
            'not-found'             => 'Not found.',
            'not-found-record'      => 'Record not found.',
            'method-not-allowed'    => 'Method not allowed.',
            'database-error'        => 'A database error occurred. Please try again later.',
            'duplicate-entry'       => 'Duplicate entry.',
            'server-error'          => 'An unexpected error occurred. Please contact support with the exception ID.',
        ];

        foreach ($keys as $key => $expected) {
            $this->assertEquals(
                $expected,
                Lang::get("core-foundation::http.{$key}"),
                "Translation key [http.{$key}] did not resolve correctly"
            );
        }
    }

    public function test_lang_get_resolves_feature_keys(): void
    {
        $this->assertEquals('Features fetched successfully.', Lang::get('core-foundation::features.fetch-all'));
        $this->assertEquals('Feature fetched successfully.', Lang::get('core-foundation::features.fetch-one'));
    }

    public function test_lang_get_resolves_feature_not_found_with_replacement(): void
    {
        $result = Lang::get('core-foundation::features.not-found', ['feature' => 'my-flag']);

        $this->assertEquals('Feature [my-flag] is not defined.', $result);
    }

    public function test_lang_get_returns_key_when_no_translation_exists(): void
    {
        // Missing key falls back to the key itself — standard Laravel behaviour
        $result = Lang::get('core-foundation::http.nonexistent-key');

        $this->assertStringContainsString('nonexistent-key', $result);
    }

    public function test_lang_get_passes_locale_override(): void
    {
        // Non-existent locale falls back gracefully — just testing the parameter is accepted
        $result = Lang::get('core-foundation::http.unauthenticated', [], 'xx');

        $this->assertIsString($result);
    }

    // =========================================================================
    // HasLang::lang() — instance method with prefix derivation
    // =========================================================================

    public function test_lang_prefix_derived_from_controller_class_name(): void
    {
        $controller = new ProductController;

        // ProductController → strips 'Controller' suffix → lowercase → 'product'
        $this->assertEquals('product', $controller->getPrefix());
    }

    public function test_lang_derives_prefix_and_prepends_to_simple_key(): void
    {
        $controller = new ProductController;

        // No translation file exists for 'product.fetch-success' —
        // Laravel returns the key itself, confirming the prefix was applied.
        $result = $controller->getMessage('fetch-success');

        $this->assertStringContainsString('product.fetch-success', $result);
    }

    public function test_lang_bypasses_prefix_for_fully_qualified_key(): void
    {
        $controller = new ProductController;

        // Key contains a dot → used as-is, prefix is skipped
        $result = $controller->getMessage('core-foundation::http.unauthenticated');

        $this->assertEquals('Unauthenticated.', $result);
    }

    public function test_lang_passes_replace_parameters(): void
    {
        $controller = new ProductController;

        $result = $controller->getMessage(
            'core-foundation::features.not-found',
            ['feature' => 'checkout-v2']
        );

        $this->assertEquals('Feature [checkout-v2] is not defined.', $result);
    }

    public function test_lang_uses_overridden_prefix(): void
    {
        $controller = new CustomPrefixController;

        // CustomPrefixController::langPrefix() returns 'orders'
        $result = $controller->getMessage('create-success');

        // No translation file for 'orders.create-success' — Laravel returns key
        $this->assertStringContainsString('orders.create-success', $result);
    }

    public function test_lang_method_is_final_and_cannot_be_overridden(): void
    {
        $reflection = new ReflectionClass(HasLang::class);
        $method = $reflection->getMethod('lang');

        $this->assertTrue($method->isFinal());
    }
}
