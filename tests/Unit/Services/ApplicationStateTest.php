<?php

namespace CoreFoundation\Tests\Unit\Services;

use Illuminate\Support\Facades\Context;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Services\ApplicationState;

class TestState extends ApplicationState
{
    protected function prefix(): string
    {
        return 'test';
    }

    public function setFoo($value)
    {
        return $this->set('foo', $value);
    }

    public function getFoo()
    {
        return $this->get('foo');
    }

    public function setPublic($key, $value)
    {
        return $this->set($key, $value);
    }

    public function isMissing($key)
    {
        return $this->missing($key);
    }
}

class ApplicationStateTest extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Context::flush();
    }

    public function test_it_namespaces_keys(): void
    {
        $state = new TestState;
        $state->setFoo('bar');

        $this->assertEquals('bar', Context::get('test.foo'));
        $this->assertEquals('bar', $state->getFoo());
    }

    public function test_it_can_get_snapshot(): void
    {
        $state = new TestState;
        $state->setFoo('bar');
        $state->setPublic('baz', 'qux');

        $snapshot = $state->snapshot();

        $this->assertArrayHasKey('foo', $snapshot);
        $this->assertArrayHasKey('baz', $snapshot);
        $this->assertEquals('bar', $snapshot['foo']);
    }

    public function test_it_can_flush_domain_state(): void
    {
        $state = new TestState;
        $state->setFoo('bar');
        Context::add('other.key', 'stay');

        $state->flush();

        $this->assertTrue($state->isMissing('foo'));
        $this->assertTrue(Context::has('other.key'));
    }
}
