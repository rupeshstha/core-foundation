<?php

namespace CoreFoundation\Tests\Unit\Repositories\Scope;

use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Tests\Stubs\Models\TestPost;
use CoreFoundation\Repositories\Scope\ScopeApplicator;

class ScopeApplicatorTest extends PackageTestCase
{
    private ScopeApplicator $applicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->applicator = new ScopeApplicator;
    }

    public function test_it_applies_a_whitelisted_scope_by_name(): void
    {
        TestPost::create(['title' => 'A', 'status' => 'active']);
        TestPost::create(['title' => 'B', 'status' => 'pending']);

        $query = TestPost::query();
        $this->applicator->apply($query, ['active'], ['active']);

        $titles = $query->pluck('title')->toArray();
        $this->assertEquals(['A'], $titles);
    }

    public function test_it_applies_a_whitelisted_scope_with_arguments(): void
    {
        TestPost::create(['title' => 'A', 'status' => 'active']);
        TestPost::create(['title' => 'B', 'status' => 'pending']);

        $query = TestPost::query();
        $this->applicator->apply($query, ['ofStatus' => ['pending']], ['ofStatus']);

        $titles = $query->pluck('title')->toArray();
        $this->assertEquals(['B'], $titles);
    }

    public function test_it_applies_multiple_whitelisted_scopes(): void
    {
        TestPost::create(['title' => 'A', 'status' => 'active', 'score' => 5]);
        TestPost::create(['title' => 'B', 'status' => 'active', 'score' => 1]);

        $query = TestPost::query();
        $this->applicator->apply($query, ['active', 'ofStatus' => ['active']], ['active', 'ofStatus']);

        $this->assertCount(2, $query->get());
    }

    public function test_it_silently_skips_a_scope_not_in_the_whitelist(): void
    {
        TestPost::create(['title' => 'A', 'status' => 'active']);
        TestPost::create(['title' => 'B', 'status' => 'pending']);

        $query = TestPost::query();
        $this->applicator->apply($query, ['active'], []); // empty whitelist

        $titles = $query->pluck('title')->toArray();
        $this->assertEqualsCanonicalizing(['A', 'B'], $titles);
    }

    public function test_it_does_not_throw_for_an_unknown_scope_name(): void
    {
        $query = TestPost::query();
        $result = $this->applicator->apply($query, ['nonexistentScope'], []);

        $this->assertSame($query, $result);
    }

    public function test_it_returns_the_builder_unchanged_when_no_scopes_given(): void
    {
        $query = TestPost::query();
        $result = $this->applicator->apply($query, [], ['active']);

        $this->assertSame($query, $result);
    }
}
