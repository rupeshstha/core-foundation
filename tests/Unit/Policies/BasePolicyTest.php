<?php

namespace CoreFoundation\Tests\Unit\Policies;

use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Policies\BasePolicy;
use Illuminate\Auth\Access\Response;

class ConcretePolicy extends BasePolicy {}

class BasePolicyTest extends PackageTestCase
{
    private ConcretePolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new ConcretePolicy;
    }

    public function test_view_any_denies_by_default(): void
    {
        $result = $this->policy->viewAny(new \stdClass);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertFalse($result->allowed());
    }

    public function test_create_denies_by_default(): void
    {
        $result = $this->policy->create(new \stdClass);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertFalse($result->allowed());
    }

    public function test_view_denies_by_default(): void
    {
        $result = $this->policy->view(new \stdClass, new \stdClass);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertFalse($result->allowed());
    }

    public function test_update_denies_by_default(): void
    {
        $result = $this->policy->update(new \stdClass, new \stdClass);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertFalse($result->allowed());
    }

    public function test_delete_denies_by_default(): void
    {
        $result = $this->policy->delete(new \stdClass, new \stdClass);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertFalse($result->allowed());
    }

    public function test_restore_denies_by_default(): void
    {
        $result = $this->policy->restore(new \stdClass, new \stdClass);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertFalse($result->allowed());
    }

    public function test_force_delete_denies_by_default(): void
    {
        $result = $this->policy->forceDelete(new \stdClass, new \stdClass);

        $this->assertInstanceOf(Response::class, $result);
        $this->assertFalse($result->allowed());
    }

    public function test_overriding_an_ability_can_allow_access(): void
    {
        $policy = new class extends BasePolicy {
            public function viewAny(mixed $user): Response|bool
            {
                return true;
            }
        };

        $this->assertTrue($policy->viewAny(new \stdClass));
        // Other abilities still deny
        $this->assertFalse($policy->create(new \stdClass)->allowed());
    }
}
