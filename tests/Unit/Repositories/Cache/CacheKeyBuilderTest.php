<?php

namespace CoreFoundation\Tests\Unit\Repositories\Cache;

use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Tests\Stubs\Models\TestPost;
use CoreFoundation\Repositories\Cache\CacheKeyBuilder;

class CacheKeyBuilderTest extends PackageTestCase
{
    private CacheKeyBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new CacheKeyBuilder;
    }

    public function test_it_builds_key_in_table_method_hash_format(): void
    {
        $model = new TestPost;
        $key = $this->builder->build($model, 'fetchAll');

        $parts = explode(':', $key);
        $this->assertCount(3, $parts);
        $this->assertEquals('test_posts', $parts[0]);
        $this->assertEquals('fetchAll', $parts[1]);
        $this->assertNotEmpty($parts[2]);
    }

    public function test_it_produces_same_key_for_identical_inputs(): void
    {
        $model = new TestPost;

        $key1 = $this->builder->build($model, 'fetchAll', ['status' => 'active']);
        $key2 = $this->builder->build($model, 'fetchAll', ['status' => 'active']);

        $this->assertEquals($key1, $key2);
    }

    public function test_it_produces_different_keys_for_different_filters(): void
    {
        $model = new TestPost;

        $key1 = $this->builder->build($model, 'fetchAll', ['status' => 'active']);
        $key2 = $this->builder->build($model, 'fetchAll', ['status' => 'pending']);

        $this->assertNotEquals($key1, $key2);
    }

    public function test_it_produces_different_keys_for_different_methods(): void
    {
        $model = new TestPost;

        $key1 = $this->builder->build($model, 'fetchAll');
        $key2 = $this->builder->build($model, 'fetchById');

        $this->assertNotEquals($key1, $key2);
    }

    public function test_it_produces_same_key_regardless_of_filter_array_order(): void
    {
        $model = new TestPost;

        $key1 = $this->builder->build($model, 'fetchAll', ['b' => 2, 'a' => 1]);
        $key2 = $this->builder->build($model, 'fetchAll', ['a' => 1, 'b' => 2]);

        $this->assertEquals($key1, $key2);
    }

    public function test_it_builds_record_tag_in_table_record_id_format(): void
    {
        $model = new TestPost;
        $key = $this->builder->buildRecordTag($model, 42);

        $this->assertEquals('test_posts:record:42', $key);
    }

    public function test_it_builds_record_tag_for_string_id(): void
    {
        $model = new TestPost;
        $key = $this->builder->buildRecordTag($model, 'uuid-abc-123');

        $this->assertEquals('test_posts:record:uuid-abc-123', $key);
    }

    public function test_it_differentiates_keys_by_relations(): void
    {
        $model = new TestPost;

        $key1 = $this->builder->build($model, 'fetchAll', [], ['comments']);
        $key2 = $this->builder->build($model, 'fetchAll', [], []);

        $this->assertNotEquals($key1, $key2);
    }

    public function test_it_differentiates_keys_by_extra_params(): void
    {
        $model = new TestPost;

        $key1 = $this->builder->build($model, 'fetchAll', [], [], [], ['page' => 1]);
        $key2 = $this->builder->build($model, 'fetchAll', [], [], [], ['page' => 2]);

        $this->assertNotEquals($key1, $key2);
    }
}
