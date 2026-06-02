<?php

namespace CoreFoundation\Tests\Feature\Exceptions;

use RuntimeException;
use CoreFoundation\Tests\PackageTestCase;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Contracts\Debug\ShouldntReport;
use Illuminate\Validation\ValidationException;
use CoreFoundation\Exceptions\BaseApiException;
use CoreFoundation\Exceptions\ExceptionRenderer;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

class SilentDomainException extends BaseApiException implements ShouldntReport
{
    protected int $status = 404;

    public function __construct()
    {
        parent::__construct('Resource not found.');
    }
}

class ExceptionRendererTest extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Ensure renderers are registered on the already-resolved handler.
        // In Testbench the handler is resolved before ServiceProvider boot completes,
        // so we register directly here to guarantee correct ordering in the test.
        $handler = $this->app->make(Handler::class);
        ExceptionRenderer::register(
            new Exceptions($handler)
        );

        // Register test routes that throw specific exceptions
        $this->app['router']->get('/_test/validation', function () {
            throw ValidationException::withMessages(['field' => ['The field is required.']]);
        });

        $this->app['router']->get('/_test/model-not-found', function () {
            throw (new ModelNotFoundException)->setModel('App\Models\User');
        });

        $this->app['router']->get('/_test/not-found', function () {
            throw new NotFoundHttpException('Route not found.');
        });

        $this->app['router']->get('/_test/method-not-allowed', function () {
            throw new MethodNotAllowedHttpException(['GET', 'POST']);
        });

        $this->app['router']->get('/_test/unauthenticated', function () {
            throw new AuthenticationException;
        });

        $this->app['router']->get('/_test/unauthorized', function () {
            throw new AuthorizationException;
        });

        $this->app['router']->get('/_test/unexpected', function () {
            throw new RuntimeException('Something broke');
        });

        $this->app['router']->get('/_test/domain', function () {
            throw new SilentDomainException;
        });
    }

    public function test_validation_exception_returns_422_with_errors(): void
    {
        $response = $this->getJson('/_test/validation');

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
        $response->assertJsonPath('errors.field.0', 'The field is required.');
    }

    public function test_model_not_found_returns_404(): void
    {
        $response = $this->getJson('/_test/model-not-found');

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Record not found.');
    }

    public function test_not_found_http_exception_returns_404(): void
    {
        $response = $this->getJson('/_test/not-found');

        $response->assertStatus(404);
        $response->assertJsonStructure(['message']);
    }

    public function test_method_not_allowed_returns_405(): void
    {
        $response = $this->getJson('/_test/method-not-allowed');

        $response->assertStatus(405);
        $response->assertJsonPath('message', 'Method not allowed.');
    }

    public function test_authentication_exception_returns_401(): void
    {
        $response = $this->getJson('/_test/unauthenticated');

        $response->assertStatus(401);
        $response->assertJsonPath('message', 'Unauthenticated.');
    }

    public function test_authorization_exception_returns_403(): void
    {
        $response = $this->getJson('/_test/unauthorized');

        $response->assertStatus(403);
        $response->assertJsonPath('message', 'This action is unauthorized.');
    }

    public function test_unexpected_throwable_returns_500_with_exception_id(): void
    {
        $response = $this->getJson('/_test/unexpected');

        $response->assertStatus(500);
        $response->assertJsonStructure(['message', 'errors', 'exception_id']);
        $this->assertNotEmpty($response->json('exception_id'));
    }

    public function test_domain_exception_renders_using_own_render_method(): void
    {
        $response = $this->getJson('/_test/domain');

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Resource not found.');
        // BaseApiException does not include exception_id
        $this->assertArrayNotHasKey('exception_id', $response->json());
    }
}
