<?php

namespace CoreFoundation\Tests\Unit\Exceptions;

use Throwable;
use Illuminate\Http\Request;
use CoreFoundation\Tests\PackageTestCase;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\Exceptions\BaseApiException;

class OrderNotFoundException extends BaseApiException
{
    protected int $status = Response::HTTP_NOT_FOUND;

    public function __construct(string $message = 'Order not found.', array $errors = [], ?int $status = null, ?Throwable $previous = null)
    {
        parent::__construct($message, $errors, $status, $previous);
    }
}

class PaymentException extends BaseApiException
{
    protected int $status = Response::HTTP_PAYMENT_REQUIRED;
}

class BaseApiExceptionExtendedTest extends PackageTestCase
{
    public function test_get_status_returns_configured_status(): void
    {
        $exception = new OrderNotFoundException;
        $this->assertEquals(Response::HTTP_NOT_FOUND, $exception->getStatus());
    }

    public function test_get_errors_returns_empty_array_by_default(): void
    {
        $exception = new OrderNotFoundException;
        $this->assertEquals([], $exception->getErrors());
    }

    public function test_get_errors_returns_constructor_errors(): void
    {
        $exception = new OrderNotFoundException(errors: ['id' => ['Not found.']]);
        $this->assertEquals(['id' => ['Not found.']], $exception->getErrors());
    }

    public function test_render_uses_configured_status_code(): void
    {
        $exception = new OrderNotFoundException;
        $response = $exception->render(Request::create('/'));

        $this->assertEquals(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function test_render_uses_default_500_when_no_status_set(): void
    {
        $exception = new class extends BaseApiException {};
        $response = $exception->render(Request::create('/'));

        $this->assertEquals(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
    }

    public function test_constructor_overrides_message(): void
    {
        $exception = new OrderNotFoundException('Custom message');
        $this->assertEquals('Custom message', $exception->getMessage());
    }

    public function test_constructor_overrides_status(): void
    {
        $exception = new OrderNotFoundException('', [], 503);
        $this->assertEquals(503, $exception->getStatus());
    }

    public function test_render_includes_field_errors(): void
    {
        $exception = new OrderNotFoundException(
            errors: ['quantity' => ['Must be at least 1.']]
        );
        $response = $exception->render(Request::create('/'));
        $data = json_decode($response->getContent(), true);

        $this->assertEquals(['quantity' => ['Must be at least 1.']], $data['errors']);
    }

    public function test_context_includes_exception_class_and_status(): void
    {
        $exception = new OrderNotFoundException;
        $context = $exception->context();

        $this->assertEquals(OrderNotFoundException::class, $context['exception']);
        $this->assertEquals(Response::HTTP_NOT_FOUND, $context['status']);
    }

    public function test_render_uses_subclass_message_property(): void
    {
        $exception = new OrderNotFoundException;
        $response = $exception->render(Request::create('/'));
        $data = json_decode($response->getContent(), true);

        $this->assertEquals('Order not found.', $data['message']);
    }
}
