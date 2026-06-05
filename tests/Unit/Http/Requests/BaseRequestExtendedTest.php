<?php

namespace CoreFoundation\Tests\Unit\Http\Requests;

use ReflectionClass;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Support\Lang;
use CoreFoundation\Http\Requests\BaseRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class ExtendedTestRequest extends BaseRequest
{
    protected function baseRules(): array
    {
        return ['title' => ['required', 'string']];
    }

    protected function storeRules(): array
    {
        return ['body' => ['required', 'string']];
    }

    protected function updateRules(): array
    {
        return ['title' => ['sometimes', 'string']];
    }
}

class UnauthorizedRequest extends BaseRequest
{
    public function authorize(): bool
    {
        return false;
    }

    protected function baseRules(): array
    {
        return ['name' => ['required']];
    }
}

class BaseRequestExtendedTest extends PackageTestCase
{
    public function test_base_rules_only_returned_for_non_post_put(): void
    {
        $request = new ExtendedTestRequest;
        $request->setMethod('GET');

        $rules = $request->rules();

        $this->assertArrayHasKey('title', $rules);
        $this->assertArrayNotHasKey('body', $rules);
    }

    public function test_is_storing_true_on_post(): void
    {
        $request = new ExtendedTestRequest;
        $request->setMethod('POST');

        $reflection = new ReflectionClass(ExtendedTestRequest::class);
        $method = $reflection->getMethod('isStoring');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($request));
    }

    public function test_is_storing_false_on_put(): void
    {
        $request = new ExtendedTestRequest;
        $request->setMethod('PUT');

        $reflection = new ReflectionClass(ExtendedTestRequest::class);
        $method = $reflection->getMethod('isStoring');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($request));
    }

    public function test_is_updating_true_on_put(): void
    {
        $request = new ExtendedTestRequest;
        $request->setMethod('PUT');

        $reflection = new ReflectionClass(ExtendedTestRequest::class);
        $method = $reflection->getMethod('isUpdating');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($request));
    }

    public function test_is_updating_true_on_patch(): void
    {
        $request = new ExtendedTestRequest;
        $request->setMethod('PATCH');

        $reflection = new ReflectionClass(ExtendedTestRequest::class);
        $method = $reflection->getMethod('isUpdating');
        $method->setAccessible(true);

        $this->assertTrue($method->invoke($request));
    }

    public function test_is_updating_false_on_post(): void
    {
        $request = new ExtendedTestRequest;
        $request->setMethod('POST');

        $reflection = new ReflectionClass(ExtendedTestRequest::class);
        $method = $reflection->getMethod('isUpdating');
        $method->setAccessible(true);

        $this->assertFalse($method->invoke($request));
    }

    public function test_update_rules_override_base_rules_for_same_key(): void
    {
        $request = new ExtendedTestRequest;
        $request->setMethod('PUT');

        $rules = $request->rules();

        // updateRules() overrides baseRules() for 'title' key
        $this->assertEquals(['sometimes', 'string'], $rules['title']);
    }

    public function test_failed_authorization_throws_http_response_exception_with_403(): void
    {
        $request = new UnauthorizedRequest;
        $request->setContainer($this->app);

        $reflection = new ReflectionClass(UnauthorizedRequest::class);
        $method = $reflection->getMethod('failedAuthorization');
        $method->setAccessible(true);

        try {
            $method->invoke($request);
            $this->fail('Expected HttpResponseException was not thrown');
        } catch (HttpResponseException $e) {
            $response = $e->getResponse();
            $this->assertEquals(403, $response->getStatusCode());
            $data = json_decode($response->getContent(), true);
            $this->assertEquals(Lang::get('core-foundation::http.unauthorized'), $data['message']);
        }
    }

    public function test_authorize_returns_true_by_default(): void
    {
        $request = new ExtendedTestRequest;
        $this->assertTrue($request->authorize());
    }

    public function test_schema_returns_empty_array_by_default(): void
    {
        $request = new ExtendedTestRequest;
        $this->assertEquals([], $request->schema());
    }

    public function test_get_request_meta_returns_null_when_no_attribute(): void
    {
        $meta = ExtendedTestRequest::getRequestMeta();
        $this->assertNull($meta);
    }
}
