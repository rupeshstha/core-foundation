<?php

namespace CoreFoundation\Tests\Unit\Repositories\Filter;

use InvalidArgumentException;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Tests\Stubs\Models\TestPost;
use CoreFoundation\Repositories\Filter\FilterApplicator;
use CoreFoundation\Repositories\Filter\Contracts\FilterOperator;
use CoreFoundation\Repositories\Filter\Operators\EqualOperator;
use Illuminate\Database\Eloquent\Builder;

class CustomTestOperator implements FilterOperator
{
    public function identifier(): string { return '__custom_'; }

    public function apply(Builder $builder, string $column, mixed $value): void
    {
        $builder->where($column, '=', 'custom:'.$value);
    }
}

class FilterApplicatorApiTest extends PackageTestCase
{
    public function test_operators_returns_all_registered_operators(): void
    {
        $operators = FilterApplicator::operators();

        $this->assertArrayHasKey('__eq_', $operators);
        $this->assertArrayHasKey('__neq_', $operators);
        $this->assertArrayHasKey('__like_', $operators);
        $this->assertArrayHasKey('__in_', $operators);
        $this->assertArrayHasKey('__null_', $operators);
        $this->assertArrayHasKey('__or_', $operators);
        $this->assertArrayHasKey('__and_', $operators);
    }

    public function test_add_operator_registers_custom_operator(): void
    {
        FilterApplicator::addOperator(new CustomTestOperator);

        $operators = FilterApplicator::operators();
        $this->assertArrayHasKey('__custom_', $operators);
        $this->assertInstanceOf(CustomTestOperator::class, $operators['__custom_']);
    }

    public function test_remove_operator_unregisters_operator(): void
    {
        $operators = FilterApplicator::operators();
        $this->assertArrayHasKey('__eq_', $operators);

        FilterApplicator::removeOperator('__eq_');

        $this->assertArrayNotHasKey('__eq_', FilterApplicator::operators());
    }

    public function test_override_operator_replaces_existing(): void
    {
        $replacement = new class implements FilterOperator {
            public function identifier(): string { return '__eq_'; }
            public function apply(Builder $builder, string $column, mixed $value): void
            {
                $builder->whereRaw("1=1");
            }
        };

        FilterApplicator::overrideOperator('__eq_', $replacement);

        $operators = FilterApplicator::operators();
        $this->assertSame($replacement, $operators['__eq_']);
    }

    public function test_override_operator_throws_for_unknown_identifier(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('not found in registry');

        FilterApplicator::overrideOperator('__nonexistent_', new EqualOperator);
    }

    public function test_reset_clears_registry_and_allows_reboot(): void
    {
        FilterApplicator::addOperator(new CustomTestOperator);
        $this->assertArrayHasKey('__custom_', FilterApplicator::operators());

        FilterApplicator::reset();

        // After reset, re-boots from config — custom operator gone
        $this->assertArrayNotHasKey('__custom_', FilterApplicator::operators());
        // Built-in operators are back
        $this->assertArrayHasKey('__eq_', FilterApplicator::operators());
    }

    public function test_pre_boot_registrations_survive_boot(): void
    {
        // addOperator before any apply() call (before boot)
        FilterApplicator::reset();
        FilterApplicator::addOperator(new CustomTestOperator);

        // Now trigger boot via operators()
        $operators = FilterApplicator::operators();

        $this->assertArrayHasKey('__custom_', $operators);
        $this->assertArrayHasKey('__eq_', $operators);
    }

    public function test_removed_operator_silently_skips_on_apply(): void
    {
        FilterApplicator::removeOperator('__eq_');

        TestPost::create(['title' => 'Only post', 'status' => 'active']);

        $query = TestPost::query();
        (new FilterApplicator)->apply($query, ['__eq_status' => 'active'], ['status']);

        // No WHERE clause added — operator was removed, so all records returned
        $this->assertCount(1, $query->get());
    }

    public function test_unknown_operator_prefix_is_silently_skipped(): void
    {
        TestPost::create(['title' => 'Post', 'status' => 'active']);

        $query = TestPost::query();
        (new FilterApplicator)->apply($query, ['__xyz_status' => 'active'], ['status']);

        $this->assertStringNotContainsString('where', $query->toSql());
    }
}
