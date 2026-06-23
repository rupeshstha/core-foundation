<?php

namespace CoreFoundation\Tests\Unit\Traits;

use CoreFoundation\Services\BaseService;
use CoreFoundation\Tests\PackageTestCase;
use Illuminate\Support\Defer\DeferredCallback;

/**
 * A concrete service exposing the protected defer methods for testing.
 */
class TestDeferrableService extends BaseService
{
    public function exposedDefer(callable $callback, ?string $name = null, bool $always = false): DeferredCallback
    {
        return $this->defer($callback, $name, $always);
    }

    public function exposedCancelDefer(string $name): void
    {
        $this->cancelDefer($name);
    }

    public function exposedDeferCacheBust(array $tags, ?string $name = null): DeferredCallback
    {
        return $this->deferCacheBust($tags, $name);
    }

    public function exposedDeferAlways(callable $callback, string $name): DeferredCallback
    {
        return $this->deferAlways($callback, $name);
    }
}

class HasDeferrableTest extends PackageTestCase
{
    private TestDeferrableService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new TestDeferrableService;
    }

    public function test_defer_returns_deferred_callback_instance(): void
    {
        $result = $this->service->exposedDefer(fn () => null);

        $this->assertInstanceOf(DeferredCallback::class, $result);
    }

    public function test_defer_with_name_returns_deferred_callback(): void
    {
        $result = $this->service->exposedDefer(fn () => null, 'test.named');

        $this->assertInstanceOf(DeferredCallback::class, $result);
    }

    public function test_defer_callback_is_invocable(): void
    {
        $executed = false;

        $deferred = $this->service->exposedDefer(function () use (&$executed) {
            $executed = true;
        });

        $deferred();

        $this->assertTrue($executed);
    }

    public function test_cancel_defer_prevents_named_callback_from_running(): void
    {
        $executed = false;

        $this->service->exposedDefer(function () use (&$executed) {
            $executed = true;
        }, 'cancel.test');

        $this->service->exposedCancelDefer('cancel.test');

        // Flush deferred callbacks — the cancelled callback must not execute
        $this->app->terminate();

        $this->assertFalse($executed);
    }

    public function test_cancel_defer_on_unknown_name_does_not_throw(): void
    {
        $this->expectNotToPerformAssertions();

        // Cancelling a name that was never registered is a no-op
        $this->service->exposedCancelDefer('nonexistent.callback');
    }

    public function test_defer_cache_bust_returns_deferred_callback(): void
    {
        $result = $this->service->exposedDeferCacheBust(['orders', 'products']);

        $this->assertInstanceOf(DeferredCallback::class, $result);
    }

    public function test_defer_cache_bust_accepts_custom_name(): void
    {
        $result = $this->service->exposedDeferCacheBust(['orders'], 'custom.cache.bust');

        $this->assertInstanceOf(DeferredCallback::class, $result);
    }

    public function test_defer_cache_bust_without_custom_name_uses_tag_based_name(): void
    {
        // Two calls with the same tags should use the same callback name (deduplication).
        // We verify neither throws and both return DeferredCallback.
        $first = $this->service->exposedDeferCacheBust(['orders']);
        $second = $this->service->exposedDeferCacheBust(['orders']);

        $this->assertInstanceOf(DeferredCallback::class, $first);
        $this->assertInstanceOf(DeferredCallback::class, $second);
    }

    public function test_defer_always_returns_deferred_callback(): void
    {
        $result = $this->service->exposedDeferAlways(fn () => null, 'cleanup.lock');

        $this->assertInstanceOf(DeferredCallback::class, $result);
    }

    public function test_defer_always_callback_is_invocable(): void
    {
        $executed = false;

        $deferred = $this->service->exposedDeferAlways(function () use (&$executed) {
            $executed = true;
        }, 'always.test');

        $deferred();

        $this->assertTrue($executed);
    }
}
