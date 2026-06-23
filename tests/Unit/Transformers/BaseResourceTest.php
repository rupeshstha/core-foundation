<?php

namespace CoreFoundation\Tests\Unit\Transformers;

use ReflectionClass;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Transformers\BaseResource;

class TestResource extends BaseResource
{
    protected function fields(Request $request): array
    {
        return [
            'id' => $this->resource->id,
            'name' => $this->resource->name,
        ];
    }

    public static function resetRegistry()
    {
        // Use reflection to reset private static properties for testing
        $reflection = new ReflectionClass(BaseResource::class);
        $additional = $reflection->getProperty('additionalFields');
        $additional->setAccessible(true);
        $additional->setValue(null, []);

        $removed = $reflection->getProperty('removedFields');
        $removed->setAccessible(true);
        $removed->setValue(null, []);
    }
}

class TimestampResource extends BaseResource
{
    protected function fields(Request $request): array
    {
        return $this->withTimestamps([
            'id' => $this->resource->id,
        ]);
    }
}

class BaseResourceTest extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        TestResource::resetRegistry();
    }

    public function test_it_transforms_to_array(): void
    {
        $resource = new TestResource((object) ['id' => 1, 'name' => 'Test']);
        $result = $resource->toArray(new Request);

        $this->assertEquals(['id' => 1, 'name' => 'Test'], $result);
    }

    public function test_it_can_add_fields_at_runtime(): void
    {
        TestResource::addField('extra', fn ($item) => 'added');

        $resource = new TestResource((object) ['id' => 1, 'name' => 'Test']);
        $result = $resource->toArray(new Request);

        $this->assertEquals('added', $result['extra']);
    }

    public function test_it_can_remove_fields_at_runtime(): void
    {
        TestResource::removeField('name');

        $resource = new TestResource((object) ['id' => 1, 'name' => 'Test']);
        $result = $resource->toArray(new Request);

        $this->assertArrayNotHasKey('name', $result);
        $this->assertArrayHasKey('id', $result);
    }

    public function test_with_timestamps_merges_created_at_and_updated_at(): void
    {
        $now = now();
        $model = new class extends Model
        {
            public $id;
        };
        $model->id = 1;
        $model->setRawAttributes([
            'created_at' => $now,
            'updated_at' => $now,
        ], true);

        $resource = new TimestampResource($model);
        $result = $resource->toArray(new Request);

        $this->assertArrayHasKey('id', $result);
        $this->assertArrayHasKey('created_at', $result);
        $this->assertArrayHasKey('updated_at', $result);
        $this->assertSame($now->toISOString(), $result['created_at']);
        $this->assertSame($now->toISOString(), $result['updated_at']);
    }

    public function test_with_timestamps_returns_null_when_timestamps_are_null(): void
    {
        $model = new class extends Model
        {
            public $id;
        };
        $model->id = 1;
        $model->setRawAttributes([
            'created_at' => null,
            'updated_at' => null,
        ], true);

        $resource = new TimestampResource($model);
        $result = $resource->toArray(new Request);

        $this->assertNull($result['created_at']);
        $this->assertNull($result['updated_at']);
    }

    public function test_with_timestamps_does_not_override_existing_fields(): void
    {
        $now = now();
        $model = new class extends Model
        {
            public $id;
        };
        $model->id = 42;
        $model->setRawAttributes([
            'created_at' => $now,
            'updated_at' => $now,
        ], true);

        $resource = new TimestampResource($model);
        $result = $resource->toArray(new Request);

        // id from fields() should survive
        $this->assertEquals(42, $result['id']);
    }
}
