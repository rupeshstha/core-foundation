<?php

namespace CoreFoundation\Tests\Unit\Repositories\Filter;

use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Tests\Stubs\Models\TestPost;
use CoreFoundation\Repositories\Filter\FilterApplicator;
use CoreFoundation\Repositories\Filter\Operators\InOperator;
use CoreFoundation\Repositories\Filter\Operators\OrOperator;
use CoreFoundation\Repositories\Filter\Operators\AndOperator;
use CoreFoundation\Repositories\Filter\Operators\LikeOperator;
use CoreFoundation\Repositories\Filter\Operators\EqualOperator;
use CoreFoundation\Repositories\Filter\Operators\NotInOperator;
use CoreFoundation\Repositories\Filter\Operators\IsNullOperator;
use CoreFoundation\Repositories\Filter\Operators\NotLikeOperator;
use CoreFoundation\Repositories\Filter\Operators\LessThanOperator;
use CoreFoundation\Repositories\Filter\Operators\NotEqualOperator;
use CoreFoundation\Repositories\Filter\Operators\IsNotNullOperator;
use CoreFoundation\Repositories\Filter\Operators\GreaterThanOperator;
use CoreFoundation\Repositories\Filter\Operators\LessThanOrEqualOperator;
use CoreFoundation\Repositories\Filter\Operators\GreaterThanOrEqualOperator;

class FilterOperatorsTest extends PackageTestCase
{
    public function test_each_operator_returns_correct_identifier(): void
    {
        $this->assertEquals('__eq_', (new EqualOperator)->identifier());
        $this->assertEquals('__neq_', (new NotEqualOperator)->identifier());
        $this->assertEquals('__gt_', (new GreaterThanOperator)->identifier());
        $this->assertEquals('__gte_', (new GreaterThanOrEqualOperator)->identifier());
        $this->assertEquals('__lt_', (new LessThanOperator)->identifier());
        $this->assertEquals('__lte_', (new LessThanOrEqualOperator)->identifier());
        $this->assertEquals('__like_', (new LikeOperator)->identifier());
        $this->assertEquals('__nlike_', (new NotLikeOperator)->identifier());
        $this->assertEquals('__null_', (new IsNullOperator)->identifier());
        $this->assertEquals('__nnull_', (new IsNotNullOperator)->identifier());
        $this->assertEquals('__in_', (new InOperator)->identifier());
        $this->assertEquals('__nin_', (new NotInOperator)->identifier());
        $this->assertEquals('__or_', (new OrOperator)->identifier());
        $this->assertEquals('__and_', (new AndOperator)->identifier());
    }

    public function test_gte_operator_filters_correctly(): void
    {
        TestPost::create(['title' => 'A', 'score' => 5.0]);
        TestPost::create(['title' => 'B', 'score' => 10.0]);
        TestPost::create(['title' => 'C', 'score' => 3.0]);

        $applicator = new FilterApplicator;
        $query = TestPost::query();
        $applicator->apply($query, ['__gte_score' => 5], ['score']);

        $results = $query->get();
        $this->assertCount(2, $results);
        $this->assertContains('A', $results->pluck('title')->toArray());
        $this->assertContains('B', $results->pluck('title')->toArray());
    }

    public function test_lte_operator_filters_correctly(): void
    {
        TestPost::create(['title' => 'A', 'score' => 5.0]);
        TestPost::create(['title' => 'B', 'score' => 10.0]);
        TestPost::create(['title' => 'C', 'score' => 3.0]);

        $applicator = new FilterApplicator;
        $query = TestPost::query();
        $applicator->apply($query, ['__lte_score' => 5], ['score']);

        $results = $query->get();
        $this->assertCount(2, $results);
        $this->assertContains('A', $results->pluck('title')->toArray());
        $this->assertContains('C', $results->pluck('title')->toArray());
    }

    public function test_lt_operator_filters_correctly(): void
    {
        TestPost::create(['title' => 'A', 'score' => 5.0]);
        TestPost::create(['title' => 'B', 'score' => 10.0]);
        TestPost::create(['title' => 'C', 'score' => 3.0]);

        $applicator = new FilterApplicator;
        $query = TestPost::query();
        $applicator->apply($query, ['__lt_score' => 5], ['score']);

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('C', $results->first()->title);
    }

    public function test_nlike_operator_filters_correctly(): void
    {
        TestPost::create(['title' => 'Laravel Tutorial']);
        TestPost::create(['title' => 'PHP Tips']);
        TestPost::create(['title' => 'Laravel Best Practices']);

        $applicator = new FilterApplicator;
        $query = TestPost::query();
        $applicator->apply($query, ['__nlike_title' => 'Laravel'], ['title']);

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('PHP Tips', $results->first()->title);
    }

    public function test_nin_operator_filters_correctly(): void
    {
        TestPost::create(['title' => 'A', 'status' => 'active']);
        TestPost::create(['title' => 'B', 'status' => 'pending']);
        TestPost::create(['title' => 'C', 'status' => 'draft']);

        $applicator = new FilterApplicator;
        $query = TestPost::query();
        $applicator->apply($query, ['__nin_status' => ['active', 'pending']], ['status']);

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('C', $results->first()->title);
    }

    public function test_and_operator_applies_nested_conditions(): void
    {
        TestPost::create(['title' => 'X', 'status' => 'active', 'score' => 10.0]);
        TestPost::create(['title' => 'Y', 'status' => 'active', 'score' => 2.0]);
        TestPost::create(['title' => 'Z', 'status' => 'pending', 'score' => 10.0]);

        $applicator = new FilterApplicator;
        $query = TestPost::query();
        $applicator->apply($query, [
            '__and_' => [
                '__eq_status' => 'active',
                '__gt_score' => 5,
            ],
        ], ['status', 'score']);

        $results = $query->get();
        $this->assertCount(1, $results);
        $this->assertEquals('X', $results->first()->title);
    }
}
