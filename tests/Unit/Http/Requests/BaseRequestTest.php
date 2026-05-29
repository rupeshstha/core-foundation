<?php

namespace CoreFoundation\Tests\Unit\Http\Requests;

use ReflectionClass;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Http\Requests\BaseRequest;

class TestRequest extends BaseRequest
{
    protected function baseRules(): array
    {
        return ['name' => 'required'];
    }

    protected function storeRules(): array
    {
        return ['password' => 'required'];
    }

    protected function updateRules(): array
    {
        return ['name' => 'sometimes'];
    }
}

class BaseRequestTest extends PackageTestCase
{
    public function test_it_merges_store_rules(): void
    {
        $request = new TestRequest;
        $request->setMethod('POST');

        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('password', $rules);
        $this->assertEquals('required', $rules['name']);
    }

    public function test_it_merges_update_rules(): void
    {
        $request = new TestRequest;
        $request->setMethod('PUT');

        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayNotHasKey('password', $rules);
        $this->assertEquals('sometimes', $rules['name']);
    }

    public function test_it_can_merge_route_parameters(): void
    {
        $request = new TestRequest;
        $request->setContainer($this->app);

        // Mock route parameter
        $route = new Route('POST', '/test/{id}', []);
        $route->bind(new Request);
        $route->setParameter('id', 123);
        $request->setRouteResolver(fn () => $route);

        // Use reflection to call protected method mergeRouteParameters
        $reflection = new ReflectionClass(TestRequest::class);
        $method = $reflection->getMethod('mergeRouteParameters');
        $method->setAccessible(true);
        $method->invoke($request, ['id']);

        $this->assertEquals(123, $request->input('id'));
    }
}
