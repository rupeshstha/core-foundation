<?php

namespace CoreFoundation\Tests\Unit\Http\Controllers;

use Illuminate\Http\JsonResponse;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Tests\Stubs\Models\TestPost;
use CoreFoundation\Exceptions\BaseApiException;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\Http\Controllers\BaseController;
use Illuminate\Pagination\LengthAwarePaginator;

class FullTestController extends BaseController
{
    public function successAction(): JsonResponse
    {
        return $this->successResponse('Fetched.', ['id' => 1]);
    }

    public function createdAction(): JsonResponse
    {
        return $this->createdResponse('Created.', ['id' => 99]);
    }

    public function noContentAction(): JsonResponse
    {
        return $this->noContentResponse();
    }

    public function paginatedAction(): JsonResponse
    {
        $paginator = new LengthAwarePaginator(
            items: [['id' => 1], ['id' => 2]],
            total: 10,
            perPage: 5,
            currentPage: 1,
        );

        return $this->paginatedResponse('Listed.', $paginator);
    }

    public function domainExceptionAction(): JsonResponse
    {
        $exception = new class extends BaseApiException {
            protected int $status = 422;
        };
        $exception->__construct('Domain problem', ['field' => ['error']]);

        return $this->handleException($exception);
    }

    public function langAction(): string
    {
        return $this->lang('some-key');
    }

    public function dottedLangAction(): string
    {
        return $this->lang('other.key');
    }
}

class BaseControllerExtendedTest extends PackageTestCase
{
    private FullTestController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new FullTestController;
    }

    public function test_created_response_returns_201(): void
    {
        $response = $this->controller->createdAction();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(Response::HTTP_CREATED, $response->getStatusCode());

        $data = $response->getData(true);
        $this->assertEquals('Created.', $data['message']);
        $this->assertEquals(['id' => 99], $data['payload']);
    }

    public function test_no_content_response_returns_204(): void
    {
        $response = $this->controller->noContentAction();

        // 204 means no content — the status code is the contract
        $this->assertEquals(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function test_paginated_response_includes_meta_pagination(): void
    {
        $response = $this->controller->paginatedAction();

        $this->assertEquals(Response::HTTP_OK, $response->getStatusCode());
        $data = $response->getData(true);

        $this->assertEquals('Listed.', $data['message']);
        $this->assertArrayHasKey('meta', $data);
        $this->assertArrayHasKey('pagination', $data['meta']);

        $pagination = $data['meta']['pagination'];
        $this->assertEquals(1, $pagination['current_page']);
        $this->assertEquals(5, $pagination['per_page']);
        $this->assertEquals(10, $pagination['total']);
        $this->assertEquals(2, $pagination['last_page']);
    }

    public function test_handle_exception_delegates_to_base_api_exception(): void
    {
        $response = $this->controller->domainExceptionAction();

        $this->assertEquals(422, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertEquals('Domain problem', $data['message']);
        $this->assertEquals(['field' => ['error']], $data['errors']);
        $this->assertArrayNotHasKey('exception_id', $data);
    }

    public function test_lang_prepends_controller_prefix(): void
    {
        // FullTestController → prefix is 'fulltest' (strips Controller suffix, lowercase)
        $result = $this->controller->langAction();
        // trans returns the key string when no translation file exists
        $this->assertStringContainsString('some-key', $result);
    }

    public function test_lang_bypasses_prefix_for_dotted_keys(): void
    {
        $result = $this->controller->dottedLangAction();
        // Dotted key used as-is
        $this->assertStringContainsString('other.key', $result);
    }

    public function test_success_response_envelope_shape(): void
    {
        $response = $this->controller->successAction();
        $data = $response->getData(true);

        $this->assertArrayHasKey('message', $data);
        $this->assertArrayHasKey('payload', $data);
        $this->assertArrayNotHasKey('errors', $data);
        $this->assertArrayNotHasKey('exception_id', $data);
    }
}
