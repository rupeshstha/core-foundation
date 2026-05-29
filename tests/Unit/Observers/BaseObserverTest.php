<?php

namespace CoreFoundation\Tests\Unit\Observers;

use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Observers\BaseObserver;

class ConcreteObserver extends BaseObserver {}

class BaseObserverTest extends PackageTestCase
{
    private ConcreteObserver $observer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->observer = new ConcreteObserver;
    }

    public function test_creating_is_no_op_by_default(): void
    {
        $result = $this->observer->creating(new \stdClass);
        $this->assertNull($result);
    }

    public function test_created_is_no_op_by_default(): void
    {
        $result = $this->observer->created(new \stdClass);
        $this->assertNull($result);
    }

    public function test_updating_is_no_op_by_default(): void
    {
        $result = $this->observer->updating(new \stdClass);
        $this->assertNull($result);
    }

    public function test_updated_is_no_op_by_default(): void
    {
        $result = $this->observer->updated(new \stdClass);
        $this->assertNull($result);
    }

    public function test_saving_is_no_op_by_default(): void
    {
        $result = $this->observer->saving(new \stdClass);
        $this->assertNull($result);
    }

    public function test_saved_is_no_op_by_default(): void
    {
        $result = $this->observer->saved(new \stdClass);
        $this->assertNull($result);
    }

    public function test_deleting_is_no_op_by_default(): void
    {
        $result = $this->observer->deleting(new \stdClass);
        $this->assertNull($result);
    }

    public function test_deleted_is_no_op_by_default(): void
    {
        $result = $this->observer->deleted(new \stdClass);
        $this->assertNull($result);
    }

    public function test_restoring_is_no_op_by_default(): void
    {
        $result = $this->observer->restoring(new \stdClass);
        $this->assertNull($result);
    }

    public function test_restored_is_no_op_by_default(): void
    {
        $result = $this->observer->restored(new \stdClass);
        $this->assertNull($result);
    }

    public function test_force_deleting_is_no_op_by_default(): void
    {
        $result = $this->observer->forceDeleting(new \stdClass);
        $this->assertNull($result);
    }

    public function test_force_deleted_is_no_op_by_default(): void
    {
        $result = $this->observer->forceDeleted(new \stdClass);
        $this->assertNull($result);
    }

    public function test_replicating_is_no_op_by_default(): void
    {
        $result = $this->observer->replicating(new \stdClass);
        $this->assertNull($result);
    }

    public function test_overriding_lifecycle_method_is_called(): void
    {
        $called = false;
        $observer = new class extends BaseObserver {
            public bool $wasCalled = false;

            public function created(mixed $model): void
            {
                $this->wasCalled = true;
            }
        };

        $observer->created(new \stdClass);
        $this->assertTrue($observer->wasCalled);
    }
}
