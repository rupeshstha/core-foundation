<?php

namespace CoreFoundation\Tests\Unit\Traits;

use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Traits\HasCacheable;

/**
 * A concrete class exposing the protected cache methods for testing.
 */
class TestCacheableSubject
{
    use HasCacheable;

    public function exposedCacheForever(array $tags, string $key, callable $closure): mixed
    {
        return $this->cacheForever($tags, $key, $closure);
    }

    public function exposedCacheTtl(array $tags, string $key, int $ttl, callable $closure): mixed
    {
        return $this->cacheTtl($tags, $key, $ttl, $closure);
    }

    public function exposedBustCache(array $tags): void
    {
        $this->bustCache($tags);
    }

    public function exposedForgetCache(array $tags, string $key): void
    {
        $this->forgetCache($tags, $key);
    }
}

class HasCacheableTest extends PackageTestCase
{
    private TestCacheableSubject $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new TestCacheableSubject;
    }

    public function test_cache_forever_returns_closure_result_on_miss(): void
    {
        $result = $this->subject->exposedCacheForever(['orders'], 'orders.detail.1', fn () => 'fresh');

        $this->assertSame('fresh', $result);
    }

    public function test_cache_forever_returns_cached_value_without_calling_closure_again(): void
    {
        $calls = 0;
        $closure = function () use (&$calls) {
            $calls++;

            return 'value';
        };

        $this->subject->exposedCacheForever(['orders'], 'orders.detail.1', $closure);
        $this->subject->exposedCacheForever(['orders'], 'orders.detail.1', $closure);

        $this->assertSame(1, $calls);
    }

    public function test_cache_ttl_returns_closure_result_on_miss(): void
    {
        $result = $this->subject->exposedCacheTtl(['orders'], 'orders.summary', 3600, fn () => 'fresh');

        $this->assertSame('fresh', $result);
    }

    public function test_cache_ttl_returns_cached_value_without_calling_closure_again(): void
    {
        $calls = 0;
        $closure = function () use (&$calls) {
            $calls++;

            return 'value';
        };

        $this->subject->exposedCacheTtl(['orders'], 'orders.summary', 3600, $closure);
        $this->subject->exposedCacheTtl(['orders'], 'orders.summary', 3600, $closure);

        $this->assertSame(1, $calls);
    }

    public function test_bust_cache_invalidates_entries_under_the_given_tag(): void
    {
        $calls = 0;
        $closure = function () use (&$calls) {
            $calls++;

            return 'value';
        };

        $this->subject->exposedCacheForever(['orders'], 'orders.detail.1', $closure);
        $this->subject->exposedBustCache(['orders']);
        $this->subject->exposedCacheForever(['orders'], 'orders.detail.1', $closure);

        $this->assertSame(2, $calls);
    }

    public function test_bust_cache_does_not_invalidate_entries_under_a_different_tag(): void
    {
        $calls = 0;
        $closure = function () use (&$calls) {
            $calls++;

            return 'value';
        };

        $this->subject->exposedCacheForever(['orders'], 'orders.detail.1', $closure);
        $this->subject->exposedBustCache(['products']);
        $this->subject->exposedCacheForever(['orders'], 'orders.detail.1', $closure);

        $this->assertSame(1, $calls);
    }

    public function test_forget_cache_removes_single_key_without_flushing_the_whole_tag(): void
    {
        $calls = 0;
        $closure = function () use (&$calls) {
            $calls++;

            return 'value';
        };

        $this->subject->exposedCacheForever(['orders'], 'orders.detail.1', $closure);
        $this->subject->exposedCacheForever(['orders'], 'orders.detail.2', $closure);

        $this->subject->exposedForgetCache(['orders'], 'orders.detail.1');

        // Forgotten key recomputes
        $this->subject->exposedCacheForever(['orders'], 'orders.detail.1', $closure);
        // Untouched key under the same tag stays cached
        $this->subject->exposedCacheForever(['orders'], 'orders.detail.2', $closure);

        $this->assertSame(3, $calls);
    }
}
