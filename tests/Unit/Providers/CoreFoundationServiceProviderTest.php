<?php

namespace CoreFoundation\Tests\Unit\Providers;

use ReflectionMethod;
use InvalidArgumentException;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Providers\CoreFoundationServiceProvider;

class CoreFoundationServiceProviderTest extends PackageTestCase
{
    private function invokeGuard(): void
    {
        $provider = new CoreFoundationServiceProvider($this->app);
        $method = new ReflectionMethod($provider, 'assertCacheDriverSupportsTags');
        $method->setAccessible(true);
        $method->invoke($provider);
    }

    public function test_it_throws_for_a_driver_that_does_not_support_tags(): void
    {
        config(['cache.default' => 'file']);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('does not support Cache::tags()');

        $this->invokeGuard();
    }

    public function test_it_does_not_throw_for_the_array_driver(): void
    {
        config(['cache.default' => 'array']);

        $this->invokeGuard();
        $this->assertTrue(true);
    }

    public function test_it_skips_the_check_entirely_when_repository_caching_is_disabled(): void
    {
        config(['cache.default' => 'file']);
        config(['core-foundation.cache.global' => false]);

        $this->invokeGuard();
        $this->assertTrue(true);
    }
}
