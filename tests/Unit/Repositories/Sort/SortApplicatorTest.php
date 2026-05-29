<?php

namespace CoreFoundation\Tests\Unit\Repositories\Sort;

use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Tests\Stubs\Models\TestPost;
use CoreFoundation\Repositories\Sort\SortApplicator;

class SortApplicatorTest extends PackageTestCase
{
    private SortApplicator $applicator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->applicator = new SortApplicator;
    }

    public function test_it_sorts_ascending_by_default(): void
    {
        TestPost::create(['title' => 'C']);
        TestPost::create(['title' => 'A']);
        TestPost::create(['title' => 'B']);

        $query = TestPost::query();
        $this->applicator->apply($query, ['title'], ['title']);

        $titles = $query->pluck('title')->toArray();
        $this->assertEquals(['A', 'B', 'C'], $titles);
    }

    public function test_it_sorts_descending_with_minus_prefix(): void
    {
        TestPost::create(['title' => 'C']);
        TestPost::create(['title' => 'A']);
        TestPost::create(['title' => 'B']);

        $query = TestPost::query();
        $this->applicator->apply($query, ['-title'], ['title']);

        $titles = $query->pluck('title')->toArray();
        $this->assertEquals(['C', 'B', 'A'], $titles);
    }

    public function test_it_sorts_ascending_using_assoc_format(): void
    {
        TestPost::create(['title' => 'C']);
        TestPost::create(['title' => 'A']);
        TestPost::create(['title' => 'B']);

        $query = TestPost::query();
        $this->applicator->apply($query, ['title' => 'asc'], ['title']);

        $titles = $query->pluck('title')->toArray();
        $this->assertEquals(['A', 'B', 'C'], $titles);
    }

    public function test_it_sorts_descending_using_assoc_format(): void
    {
        TestPost::create(['title' => 'C']);
        TestPost::create(['title' => 'A']);
        TestPost::create(['title' => 'B']);

        $query = TestPost::query();
        $this->applicator->apply($query, ['title' => 'desc'], ['title']);

        $titles = $query->pluck('title')->toArray();
        $this->assertEquals(['C', 'B', 'A'], $titles);
    }

    public function test_it_applies_multiple_sort_columns(): void
    {
        TestPost::create(['title' => 'B', 'status' => 'active']);
        TestPost::create(['title' => 'A', 'status' => 'pending']);
        TestPost::create(['title' => 'A', 'status' => 'active']);

        $query = TestPost::query();
        $this->applicator->apply($query, ['title', 'status'], ['title', 'status']);

        $results = $query->get();
        $this->assertEquals('A', $results[0]->title);
        $this->assertEquals('active', $results[0]->status);
        $this->assertEquals('A', $results[1]->title);
        $this->assertEquals('pending', $results[1]->status);
        $this->assertEquals('B', $results[2]->title);
    }

    public function test_it_skips_columns_not_in_whitelist(): void
    {
        TestPost::create(['title' => 'B']);
        TestPost::create(['title' => 'A']);

        $query = TestPost::query();
        $this->applicator->apply($query, ['-title'], []); // empty whitelist

        // No ORDER BY since title not in allowed columns
        $this->assertStringNotContainsString('order by', $query->toSql());
    }

    public function test_it_normalises_direction_to_asc_or_desc(): void
    {
        $query = TestPost::query();
        $this->applicator->apply($query, ['title' => 'DESC'], ['title']);

        $this->assertStringContainsString('order by "title" desc', $query->toSql());
    }

    public function test_it_defaults_unknown_direction_to_asc(): void
    {
        $query = TestPost::query();
        $this->applicator->apply($query, ['title' => 'random'], ['title']);

        $this->assertStringContainsString('order by "title" asc', $query->toSql());
    }
}
