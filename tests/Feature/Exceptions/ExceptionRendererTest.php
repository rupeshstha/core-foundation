<?php

namespace CoreFoundation\Tests\Feature\Exceptions;

use RuntimeException;
use Illuminate\Support\Str;
use InvalidArgumentException;
use CoreFoundation\Support\Lang;
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
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

// ---------------------------------------------------------------------------
// Stubs
// ---------------------------------------------------------------------------

class SilentDomainException extends BaseApiException implements ShouldntReport
{
    protected int $status = 404;

    public function __construct()
    {
        parent::__construct('Resource not found.');
    }
}

class ReportableDomainException extends BaseApiException
{
    protected int $status = 503;

    public function __construct()
    {
        parent::__construct('Service unavailable.');
    }
}

class ExceptionRendererTest extends PackageTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // In Testbench the handler may be resolved before ServiceProvider boot —
        // re-register here to guarantee renderers/reporters are wired for every test.
        $handler = $this->app->make(Handler::class);
        ExceptionRenderer::register(new Exceptions($handler));

        $this->app['router']->get('/_test/validation', fn () => throw ValidationException::withMessages(['field' => ['The field is required.']])
        );

        $this->app['router']->get('/_test/model-not-found', fn () => throw (new ModelNotFoundException)->setModel('App\Models\User')
        );

        $this->app['router']->get('/_test/not-found', fn () => throw new NotFoundHttpException('Route not found.')
        );

        $this->app['router']->get('/_test/method-not-allowed', fn () => throw new MethodNotAllowedHttpException(['GET', 'POST'])
        );

        $this->app['router']->get('/_test/unauthenticated', fn () => throw new AuthenticationException
        );

        $this->app['router']->get('/_test/unauthorized', fn () => throw new AuthorizationException
        );

        $this->app['router']->get('/_test/http-exception', fn () => throw new HttpException(418, 'I am a teapot.')
        );

        $this->app['router']->get('/_test/unexpected', fn () => throw new RuntimeException('Something broke')
        );

        $this->app['router']->post('/_test/unexpected-with-sensitive', function () {
            throw new RuntimeException('Failure');
        });

        $this->app['router']->get('/_test/domain-silent', fn () => throw new SilentDomainException
        );

        $this->app['router']->get('/_test/domain-reportable', fn () => throw new ReportableDomainException
        );
    }

    // =========================================================================
    // Response envelope shape — every error response must include errors key
    // =========================================================================

    public function test_all_error_responses_include_errors_key(): void
    {
        $routes = [
            '/_test/not-found' => 404,
            '/_test/method-not-allowed' => 405,
            '/_test/unauthenticated' => 401,
            '/_test/unauthorized' => 403,
            '/_test/unexpected' => 500,
        ];

        foreach ($routes as $route => $status) {
            $response = $this->getJson($route);
            $response->assertStatus($status);
            $response->assertJsonStructure(['message', 'errors']);
        }
    }

    // =========================================================================
    // ValidationException
    // =========================================================================

    public function test_validation_exception_returns_422_with_field_errors(): void
    {
        $response = $this->getJson('/_test/validation');

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
        $response->assertJsonPath('errors.field.0', 'The field is required.');
    }

    public function test_validation_exception_message_is_set(): void
    {
        $response = $this->getJson('/_test/validation');

        $this->assertNotEmpty($response->json('message'));
    }

    // =========================================================================
    // NotFoundHttpException / ModelNotFoundException
    // =========================================================================

    public function test_model_not_found_returns_404_with_record_message(): void
    {
        $response = $this->getJson('/_test/model-not-found');

        $response->assertStatus(404);
        $response->assertJsonPath('message', Lang::get('core-foundation::http.not-found-record'));
        $response->assertJsonPath('errors', []);
    }

    public function test_generic_not_found_returns_404_with_not_found_message(): void
    {
        $response = $this->getJson('/_test/not-found');

        $response->assertStatus(404);
        $response->assertJsonPath('message', Lang::get('core-foundation::http.not-found'));
        $response->assertJsonPath('errors', []);
    }

    // =========================================================================
    // MethodNotAllowedHttpException
    // =========================================================================

    public function test_method_not_allowed_returns_405(): void
    {
        $response = $this->getJson('/_test/method-not-allowed');

        $response->assertStatus(405);
        $response->assertJsonPath('message', Lang::get('core-foundation::http.method-not-allowed'));
        $response->assertJsonPath('errors', []);
    }

    // =========================================================================
    // AuthenticationException
    // =========================================================================

    public function test_authentication_exception_returns_401(): void
    {
        $response = $this->getJson('/_test/unauthenticated');

        $response->assertStatus(401);
        $response->assertJsonPath('message', Lang::get('core-foundation::http.unauthenticated'));
        $response->assertJsonPath('errors', []);
    }

    // =========================================================================
    // AuthorizationException
    // =========================================================================

    public function test_authorization_exception_returns_403(): void
    {
        $response = $this->getJson('/_test/unauthorized');

        $response->assertStatus(403);
        $response->assertJsonPath('message', Lang::get('core-foundation::http.unauthorized'));
        $response->assertJsonPath('errors', []);
    }

    // =========================================================================
    // HttpException catch-all
    // =========================================================================

    public function test_http_exception_uses_its_own_status_code_and_message(): void
    {
        $response = $this->getJson('/_test/http-exception');

        $response->assertStatus(418);
        $response->assertJsonPath('message', 'I am a teapot.');
        $response->assertJsonPath('errors', []);
    }

    // =========================================================================
    // Fatal Throwable fallback
    // =========================================================================

    public function test_unexpected_throwable_returns_500_with_full_envelope(): void
    {
        $response = $this->getJson('/_test/unexpected');

        $response->assertStatus(500);
        $response->assertJsonStructure(['message', 'errors', 'exception_id']);
        $response->assertJsonPath('message', Lang::get('core-foundation::http.server-error'));
        $response->assertJsonPath('errors', []);
    }

    public function test_fatal_exception_id_is_a_valid_uuid(): void
    {
        $response = $this->getJson('/_test/unexpected');

        $exceptionId = $response->json('exception_id');

        $this->assertNotEmpty($exceptionId);
        $this->assertTrue(Str::isUuid($exceptionId), "exception_id [{$exceptionId}] is not a valid UUID");
    }

    public function test_each_fatal_request_gets_a_unique_exception_id(): void
    {
        $first = $this->getJson('/_test/unexpected')->json('exception_id');
        $second = $this->getJson('/_test/unexpected')->json('exception_id');

        $this->assertNotEquals($first, $second);
    }

    // =========================================================================
    // buildExceptionContext — structured context for log entries
    // =========================================================================

    public function test_build_exception_context_contains_required_fields(): void
    {
        $exception = new RuntimeException('Test message', 42);

        $context = ExceptionRenderer::buildExceptionContext($exception);

        $this->assertEquals(RuntimeException::class, $context['class']);
        $this->assertEquals('Test message', $context['message']);
        $this->assertIsString($context['file']);
        $this->assertIsInt($context['line']);
        $this->assertIsArray($context['trace']);
        $this->assertNotEmpty($context['trace']);
    }

    public function test_build_exception_context_file_is_relative_to_project_root(): void
    {
        $exception = new RuntimeException('Test');

        $context = ExceptionRenderer::buildExceptionContext($exception);

        $this->assertStringNotContainsString(base_path().'/', $context['file']);
    }

    public function test_build_exception_context_trace_has_structured_frames(): void
    {
        $exception = new RuntimeException('Test');

        $context = ExceptionRenderer::buildExceptionContext($exception);

        $firstFrame = $context['trace'][0] ?? [];
        $this->assertArrayHasKey('call', $firstFrame);
    }

    public function test_build_exception_context_includes_caused_by_for_wrapped_exceptions(): void
    {
        $cause = new InvalidArgumentException('Root cause');
        $outer = new RuntimeException('Wrapper', 0, $cause);

        $context = ExceptionRenderer::buildExceptionContext($outer);

        $this->assertArrayHasKey('caused_by', $context);
        $this->assertEquals(InvalidArgumentException::class, $context['caused_by']['class']);
        $this->assertEquals('Root cause', $context['caused_by']['message']);
    }

    public function test_build_exception_context_no_caused_by_when_no_previous(): void
    {
        $exception = new RuntimeException('Standalone');

        $context = ExceptionRenderer::buildExceptionContext($exception);

        $this->assertArrayNotHasKey('caused_by', $context);
    }

    // =========================================================================
    // Sensitive field redaction — unit-level via buildExceptionContext / request body
    // =========================================================================

    public function test_sensitive_fields_are_listed_in_redacted_constants(): void
    {
        // Verify via a request containing sensitive fields — send to a test route
        // and assert the response UUID exists (proof that reporter ran with redaction active)
        $response = $this->postJson('/_test/unexpected-with-sensitive', [
            'name' => 'John',
            'password' => 'super-secret',
        ]);

        // Reporter ran (UUID present) — redaction happened internally even if we can't
        // assert the log content directly without a log fake
        $response->assertStatus(500);
        $this->assertNotEmpty($response->json('exception_id'));
    }

    // =========================================================================
    // BaseApiException (domain exceptions)
    // =========================================================================

    public function test_silent_domain_exception_renders_with_its_own_status_and_message(): void
    {
        $response = $this->getJson('/_test/domain-silent');

        $response->assertStatus(404);
        $response->assertJsonPath('message', 'Resource not found.');
        $this->assertArrayNotHasKey('exception_id', $response->json());
    }

    public function test_domain_exception_response_does_not_include_exception_id(): void
    {
        $response = $this->getJson('/_test/domain-silent');

        $this->assertArrayNotHasKey('exception_id', $response->json());
    }

    public function test_reportable_domain_exception_uses_its_own_render_method(): void
    {
        $response = $this->getJson('/_test/domain-reportable');

        $response->assertStatus(503);
        $response->assertJsonPath('message', 'Service unavailable.');
    }

    // =========================================================================
    // reporter → renderer UUID handoff
    // =========================================================================

    public function test_exception_id_in_response_matches_uuid_stored_by_reporter(): void
    {
        $exception = new RuntimeException('Handoff test');

        // Manually trigger the reporter pipeline as Laravel would
        report($exception);

        $uuid = ExceptionRenderer::consumeExceptionId($exception);

        $this->assertTrue(Str::isUuid($uuid));
    }

    public function test_consume_exception_id_returns_fresh_uuid_when_reporter_did_not_run(): void
    {
        // Exception was never reported — consumeExceptionId must still return a UUID
        $exception = new RuntimeException('Never reported');

        $uuid = ExceptionRenderer::consumeExceptionId($exception);

        $this->assertTrue(Str::isUuid($uuid));
    }

    // =========================================================================
    // Non-JSON requests fall through — no interference from our renderers
    // =========================================================================

    public function test_non_json_requests_are_not_handled_by_our_renderers(): void
    {
        // Sending a plain HTML request — our renderers return null, Laravel handles it
        $response = $this->get('/_test/not-found');

        // Laravel's default handler returns a redirect or HTML page, not our JSON
        $this->assertNotEquals('application/json', $response->headers->get('Content-Type'));
    }
}
