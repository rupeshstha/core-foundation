<?php

namespace CoreFoundation\Tests\Unit\Http\Controllers;

use Exception;
use Illuminate\Http\JsonResponse;
use CoreFoundation\Support\Lang;
use CoreFoundation\Tests\PackageTestCase;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\Http\Controllers\BaseController;

class TestController extends BaseController
{
    public function index()
    {
        return $this->successResponse('Success', ['foo' => 'bar']);
    }

    public function error()
    {
        return $this->handleException(new Exception('Test Error'));
    }
}

class BaseControllerTest extends PackageTestCase
{
    public function test_it_returns_success_response(): void
    {
        $controller = new TestController;
        $response = $controller->index();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('Success', $data['message']);
        $this->assertEquals(['foo' => 'bar'], $data['payload']);
    }

    public function test_it_handles_exceptions(): void
    {
        $controller = new TestController;
        $response = $controller->error();

        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertArrayHasKey('exception_id', $data);
        $this->assertEquals(Lang::get('core-foundation::http.server-error'), $data['message']);
    }
}
