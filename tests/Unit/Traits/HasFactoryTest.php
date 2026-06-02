<?php

namespace CoreFoundation\Tests\Unit\Traits;

use LogicException;
use CoreFoundation\Services\BaseService;
use CoreFoundation\Tests\PackageTestCase;

class FactoryOrderService extends BaseService {}

class FactorySubscriptionOrderService extends FactoryOrderService {}

class FactoryUnrelatedService extends BaseService {}

class HasFactoryTest extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        FactoryOrderService::clearPreference();
        FactoryUnrelatedService::clearPreference();
    }

    protected function tearDown(): void
    {
        FactoryOrderService::clearPreference();
        FactoryUnrelatedService::clearPreference();
        parent::tearDown();
    }

    public function test_resolve_preference_returns_self_when_no_preference_registered(): void
    {
        $service = new FactoryOrderService;

        $result = $service->resolvePreference();

        $this->assertSame($service, $result);
    }

    public function test_resolve_preference_returns_concrete_when_closure_condition_is_true(): void
    {
        FactoryOrderService::setPreference(
            concrete: FactorySubscriptionOrderService::class,
            condition: fn () => true,
        );

        $result = (new FactoryOrderService)->resolvePreference();

        $this->assertInstanceOf(FactorySubscriptionOrderService::class, $result);
    }

    public function test_resolve_preference_returns_self_when_closure_condition_is_false(): void
    {
        FactoryOrderService::setPreference(
            concrete: FactorySubscriptionOrderService::class,
            condition: fn () => false,
        );

        $service = new FactoryOrderService;
        $result = $service->resolvePreference();

        $this->assertSame($service, $result);
        $this->assertNotInstanceOf(FactorySubscriptionOrderService::class, $result);
    }

    public function test_resolve_preference_throws_logic_exception_when_concrete_does_not_extend_service(): void
    {
        FactoryOrderService::setPreference(
            concrete: FactoryUnrelatedService::class,
            condition: fn () => true,
        );

        $this->expectException(LogicException::class);
        $this->expectExceptionMessageMatches('/must extend/');

        (new FactoryOrderService)->resolvePreference();
    }

    public function test_clear_preference_removes_registered_preference(): void
    {
        FactoryOrderService::setPreference(
            concrete: FactorySubscriptionOrderService::class,
            condition: fn () => true,
        );

        FactoryOrderService::clearPreference();

        $service = new FactoryOrderService;
        $result = $service->resolvePreference();

        $this->assertSame($service, $result);
    }

    public function test_preferences_are_isolated_per_service_class(): void
    {
        FactoryOrderService::setPreference(
            concrete: FactorySubscriptionOrderService::class,
            condition: fn () => true,
        );

        // FactoryUnrelatedService has no preference — must resolve to itself
        $unrelated = new FactoryUnrelatedService;
        $result = $unrelated->resolvePreference();

        $this->assertSame($unrelated, $result);
    }

    public function test_resolve_preference_uses_string_condition_resolved_from_container(): void
    {
        // Register a condition class in the container whose handle() returns true
        $this->app->bind('test.true.condition', function () {
            return new class
            {
                public function handle(): bool
                {
                    return true;
                }
            };
        });

        FactoryOrderService::setPreference(
            concrete: FactorySubscriptionOrderService::class,
            condition: 'test.true.condition',
        );

        $result = (new FactoryOrderService)->resolvePreference();

        $this->assertInstanceOf(FactorySubscriptionOrderService::class, $result);
    }

    public function test_resolve_preference_with_false_string_condition_returns_self(): void
    {
        $this->app->bind('test.false.condition', function () {
            return new class
            {
                public function handle(): bool
                {
                    return false;
                }
            };
        });

        FactoryOrderService::setPreference(
            concrete: FactorySubscriptionOrderService::class,
            condition: 'test.false.condition',
        );

        $service = new FactoryOrderService;
        $result = $service->resolvePreference();

        $this->assertSame($service, $result);
    }

    public function test_set_preference_overwrites_previous_preference(): void
    {
        // First preference — would not trigger
        FactoryOrderService::setPreference(
            concrete: FactorySubscriptionOrderService::class,
            condition: fn () => false,
        );

        // Second preference for the same class — overwrites first
        FactoryOrderService::setPreference(
            concrete: FactorySubscriptionOrderService::class,
            condition: fn () => true,
        );

        $result = (new FactoryOrderService)->resolvePreference();

        $this->assertInstanceOf(FactorySubscriptionOrderService::class, $result);
    }
}
