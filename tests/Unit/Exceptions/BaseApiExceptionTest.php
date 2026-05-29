<?php

namespace CoreFoundation\Tests\Unit\Exceptions;

use Illuminate\Http\Request;
use CoreFoundation\Tests\PackageTestCase;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\Exceptions\BaseApiException;

class TestApiException extends BaseApiException
{
    protected int $status = Response::HTTP_BAD_REQUEST;
}

class BaseApiExceptionTest extends PackageTestCase
{
    public function test_it_renders_to_json(): void
    {
        $exception = new TestApiException('Error message', ['field' => ['error']]);
        $response = $exception->render(new Request);

        $this->assertEquals(Response::HTTP_BAD_REQUEST, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('Error message', $data['message']);
        $this->assertEquals(['field' => ['error']], $data['errors']);
    }

    public function test_it_can_get_context(): void
    {
        $exception = new TestApiException('Error message');
        $context = $exception->context();

        $this->assertArrayHasKey('exception', $context);
        $this->assertArrayHasKey('status', $context);
        $this->assertEquals(TestApiException::class, $context['exception']);
        $this->assertEquals(Response::HTTP_BAD_REQUEST, $context['status']);
    }
}
