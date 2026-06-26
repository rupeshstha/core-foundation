<?php

namespace CoreFoundation\Tests\Unit\Observers;

use Illuminate\Database\Eloquent\Model;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Observers\BaseObserver;
use CoreFoundation\Tests\Stubs\Models\TestPost;

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
        $result = $this->observer->creating(new TestPost);
        $this->assertNull($result);
    }

    public function test_created_is_no_op_by_default(): void
    {
        $result = $this->observer->created(new TestPost);
        $this->assertNull($result);
    }

    public function test_updating_is_no_op_by_default(): void
    {
        $result = $this->observer->updating(new TestPost);
        $this->assertNull($result);
    }

    public function test_updated_is_no_op_by_default(): void
    {
        $result = $this->observer->updated(new TestPost);
        $this->assertNull($result);
    }

    public function test_saving_is_no_op_by_default(): void
    {
        $result = $this->observer->saving(new TestPost);
        $this->assertNull($result);
    }

    public function test_saved_is_no_op_by_default(): void
    {
        $result = $this->observer->saved(new TestPost);
        $this->assertNull($result);
    }

    public function test_deleting_is_no_op_by_default(): void
    {
        $result = $this->observer->deleting(new TestPost);
        $this->assertNull($result);
    }

    public function test_deleted_is_no_op_by_default(): void
    {
        $result = $this->observer->deleted(new TestPost);
        $this->assertNull($result);
    }

    public function test_restoring_is_no_op_by_default(): void
    {
        $result = $this->observer->restoring(new TestPost);
        $this->assertNull($result);
    }

    public function test_restored_is_no_op_by_default(): void
    {
        $result = $this->observer->restored(new TestPost);
        $this->assertNull($result);
    }

    public function test_force_deleting_is_no_op_by_default(): void
    {
        $result = $this->observer->forceDeleting(new TestPost);
        $this->assertNull($result);
    }

    public function test_force_deleted_is_no_op_by_default(): void
    {
        $result = $this->observer->forceDeleted(new TestPost);
        $this->assertNull($result);
    }

    public function test_replicating_is_no_op_by_default(): void
    {
        $result = $this->observer->replicating(new TestPost);
        $this->assertNull($result);
    }

    public function test_overriding_lifecycle_method_is_called(): void
    {
        $called = false;
        $observer = new class extends BaseObserver
        {
            public bool $wasCalled = false;

            public function created(Model $model): void
            {
                $this->wasCalled = true;
            }
        };

        $observer->created(new TestPost);
        $this->assertTrue($observer->wasCalled);
    }
}
