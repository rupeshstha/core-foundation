<?php

namespace CoreFoundation\Tests\Unit\Transformers;

use LogicException;
use Illuminate\Http\Request;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Transformers\BaseResource;
use CoreFoundation\Transformers\BaseCollection;

class TestItemResource extends BaseResource
{
    protected function fields(Request $request): array
    {
        return ['id' => $this->resource->id];
    }
}

class TestCollection extends BaseCollection
{
    public $collects = TestItemResource::class;
}

class BaseCollectionTest extends PackageTestCase
{
    public function test_it_collects_resources(): void
    {
        $collection = new TestCollection(collect([(object) ['id' => 1], (object) ['id' => 2]]));
        $result = $collection->toArray(new Request);

        $this->assertCount(2, $result);
        $this->assertEquals(['id' => 1], $result[0]);
        $this->assertEquals(['id' => 2], $result[1]);
    }

    public function test_it_throws_exception_if_collects_missing(): void
    {
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('must declare: public $collects');

        new class(collect([])) extends BaseCollection {};
    }
}
