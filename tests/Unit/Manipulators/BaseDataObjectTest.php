<?php

namespace CoreFoundation\Tests\Unit\Manipulators;

use CoreFoundation\Attributes\Property;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Attributes\ApiResponse;
use CoreFoundation\Manipulators\BaseDataObject;

#[ApiResponse(description: 'Test Response', status: 200)]
class TestData extends BaseDataObject
{
    #[Property(type: 'string', description: 'Name')]
    public string $name;

    public function __construct(string $name)
    {
        parent::__construct(['name' => $name]);
        $this->name = $name;
    }
}

class BaseDataObjectTest extends PackageTestCase
{
    public function test_it_can_be_instantiated_from_array(): void
    {
        $data = BaseDataObject::fromArray(['foo' => 'bar']);
        $this->assertEquals('bar', $data->foo);
    }

    public function test_it_can_get_api_response_meta(): void
    {
        $meta = TestData::getResponseMeta();
        $this->assertNotNull($meta);
        $this->assertEquals('Test Response', $meta->description);
    }

    public function test_it_can_get_property_meta(): void
    {
        $meta = TestData::getPropertyMeta();
        $this->assertArrayHasKey('name', $meta);
        $this->assertEquals('string', $meta['name']->type);
    }
}
