<?php

namespace CoreFoundation\Tests\Unit\Entities;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;
use Illuminate\Database\Eloquent\Builder;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Tests\Stubs\Models\TestPost;

class ActiveScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('status', 'active');
    }
}

class DraftScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $builder->where('status', 'draft');
    }
}

class BaseModelScopeTest extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->cleanUpScopes();
    }

    protected function tearDown(): void
    {
        $this->cleanUpScopes();
        parent::tearDown();
    }

    private function cleanUpScopes(): void
    {
        TestPost::removeScope(ActiveScope::class);
        TestPost::removeScope(DraftScope::class);
        TestPost::removeScope('custom-id');
    }

    public function test_add_scope_registers_in_additional_scopes(): void
    {
        TestPost::addScope(new ActiveScope);

        $scopes = TestPost::getAdditionalScopes();
        $this->assertArrayHasKey(ActiveScope::class, $scopes);
        $this->assertInstanceOf(ActiveScope::class, $scopes[ActiveScope::class]);
    }

    public function test_add_scope_uses_class_name_as_default_identifier(): void
    {
        TestPost::addScope(new ActiveScope);

        $scopes = TestPost::getAdditionalScopes();
        $this->assertArrayHasKey(ActiveScope::class, $scopes);
    }

    public function test_add_scope_uses_custom_identifier_when_provided(): void
    {
        TestPost::addScope(new ActiveScope, 'custom-id');

        $scopes = TestPost::getAdditionalScopes();
        $this->assertArrayHasKey('custom-id', $scopes);
        $this->assertArrayNotHasKey(ActiveScope::class, $scopes);
    }

    public function test_remove_scope_deletes_from_registry(): void
    {
        TestPost::addScope(new ActiveScope);
        TestPost::removeScope(ActiveScope::class);

        $scopes = TestPost::getAdditionalScopes();
        $this->assertArrayNotHasKey(ActiveScope::class, $scopes);
    }

    public function test_get_additional_scopes_returns_all_registered_scopes(): void
    {
        TestPost::addScope(new ActiveScope);
        TestPost::addScope(new DraftScope);

        $scopes = TestPost::getAdditionalScopes();
        $this->assertCount(2, $scopes);
        $this->assertArrayHasKey(ActiveScope::class, $scopes);
        $this->assertArrayHasKey(DraftScope::class, $scopes);
    }

    public function test_scope_filters_query_results(): void
    {
        TestPost::create(['title' => 'A', 'status' => 'active']);
        TestPost::create(['title' => 'B', 'status' => 'pending']);

        TestPost::addScope(new ActiveScope);

        $results = TestPost::all();
        $this->assertCount(1, $results);
        $this->assertEquals('A', $results->first()->title);
    }
}
