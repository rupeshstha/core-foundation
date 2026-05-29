<?php

namespace CoreFoundation\Tests\Unit\Transformers;

use ReflectionClass;
use Illuminate\Http\Request;
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
}
