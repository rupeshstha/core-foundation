<?php

namespace CoreFoundation\Tests\Unit\Repositories\Filter;

use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Tests\Stubs\Models\TestPost;
use CoreFoundation\Repositories\Filter\FilterApplicator;

class FilterApplicatorTest extends PackageTestCase
{
    private FilterApplicator $applicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->applicator = new FilterApplicator;
    }

    public function test_it_applies_equal_filter(): void
    {
        $query = TestPost::query();
        $this->applicator->apply($query, ['__eq_status' => 'active'], ['status']);

        $this->assertStringContainsString('where ("status" = ?)', $query->toSql());
        $this->assertEquals(['active'], $query->getBindings());
    }

    public function test_it_applies_not_equal_filter(): void
    {
        $query = TestPost::query();
        $this->applicator->apply($query, ['__neq_status' => 'active'], ['status']);

        $this->assertStringContainsString('where ("status" != ?)', $query->toSql());
        $this->assertEquals(['active'], $query->getBindings());
    }

    public function test_it_applies_greater_than_filter(): void
    {
        $query = TestPost::query();
        $this->applicator->apply($query, ['__gt_score' => 10], ['score']);

        $this->assertStringContainsString('where ("score" > ?)', $query->toSql());
        $this->assertEquals([10], $query->getBindings());
    }

    public function test_it_applies_like_filter(): void
    {
        $query = TestPost::query();
        $this->applicator->apply($query, ['__like_title' => 'Laravel'], ['title']);

        $this->assertStringContainsString('where ("title" LIKE ?)', $query->toSql());
        $this->assertEquals(['%Laravel%'], $query->getBindings());
    }

    public function test_it_applies_in_filter(): void
    {
        $query = TestPost::query();
        $this->applicator->apply($query, ['__in_status' => ['active', 'pending']], ['status']);

        $this->assertStringContainsString('where ("status" in (?, ?))', $query->toSql());
        $this->assertEquals(['active', 'pending'], $query->getBindings());
    }

    public function test_it_applies_null_filter(): void
    {
        $query = TestPost::query();
        $this->applicator->apply($query, ['__null_deleted_at' => 1], ['deleted_at']);

        $this->assertStringContainsString('where ("deleted_at" is null)', $query->toSql());
    }

    public function test_it_applies_not_null_filter(): void
    {
        $query = TestPost::query();
        $this->applicator->apply($query, ['__nnull_deleted_at' => 1], ['deleted_at']);

        $this->assertStringContainsString('where ("deleted_at" is not null)', $query->toSql());
    }

    public function test_it_ignores_not_allowed_columns(): void
    {
        $query = TestPost::query();
        $this->applicator->apply($query, ['__eq_secret' => 'value'], ['status']);

        $this->assertStringNotContainsString('where "secret"', $query->toSql());
    }

    public function test_it_handles_or_nested_filters(): void
    {
        $query = TestPost::query();
        $this->applicator->apply($query, [
            '__or_' => [
                '__eq_status' => 'active',
                '__eq_title' => 'Title',
            ],
        ], ['status', 'title']);

        $sql = $query->toSql();
        $this->assertStringContainsString('"status" = ?', $sql);
        $this->assertStringContainsString(' or ', $sql);
        $this->assertStringContainsString('"title" = ?', $sql);
    }
}
