<?php

namespace CoreFoundation\Testing\Concerns;

use Illuminate\Testing\TestResponse;

/**
 * AssertsApiResponse
 *
 * Assertion helpers for the CoreFoundation response envelope.
 * All methods accept a TestResponse and return it — chain Laravel's native
 * assertions directly on the return value.
 *
 * ENVELOPE SHAPES ASSERTED:
 *
 *   Success:    { "message": "...", "payload": {...} }
 *   Created:    { "message": "...", "payload": {...} }  + 201
 *   Paginated:  { "message": "...", "payload": [...], "meta": { "pagination": {...} } }
 *   No content: (empty body) + 204
 *   Error:      { "message": "...", "errors": {...} }
 *   Fatal:      { "message": "...", "errors": {...}, "exception_id": "uuid" }
 *
 * USAGE IN PEST:
 *
 *   it('fetches a user', function () {
 *       $response = $this->getJson("/api/users/{$user->id}");
 *
 *       $this->assertSuccessResponse($response)
 *            ->assertJsonPath('payload.email', 'john@example.com');
 *   });
 *
 *   it('returns validation errors', function () {
 *       $response = $this->postJson('/api/users', []);
 *
 *       $this->assertValidationError($response, 'name', 'email');
 *   });
 */
trait AssertsApiResponse
{
    /**
     * Assert a 200 OK response with the CoreFoundation success envelope.
     * Pass $message to assert the exact message string.
     *
     *   $this->assertSuccessResponse($response, 'User fetched.')
     *        ->assertJsonPath('payload.name', 'John');
     */
    public function assertSuccessResponse(TestResponse $response, ?string $message = null): TestResponse
    {
        $response->assertOk()
            ->assertJsonStructure(['message', 'payload']);

        if ($message !== null) {
            $response->assertJsonPath('message', $message);
        }

        return $response;
    }

    /**
     * Assert a 201 Created response with the CoreFoundation success envelope.
     *
     *   $this->assertCreatedResponse($response, 'User created.')
     *        ->assertJsonPath('payload.id', 1);
     */
    public function assertCreatedResponse(TestResponse $response, ?string $message = null): TestResponse
    {
        $response->assertCreated()
            ->assertJsonStructure(['message', 'payload']);

        if ($message !== null) {
            $response->assertJsonPath('message', $message);
        }

        return $response;
    }

    /**
     * Assert a 204 No Content response (delete operations).
     */
    public function assertNoContentResponse(TestResponse $response): TestResponse
    {
        return $response->assertNoContent();
    }

    /**
     * Assert a 200 paginated response with pagination meta.
     *
     *   $this->assertPaginatedResponse($response)
     *        ->assertJsonPath('meta.pagination.total', 42);
     */
    public function assertPaginatedResponse(TestResponse $response, ?string $message = null): TestResponse
    {
        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'payload',
                'meta' => [
                    'pagination' => ['total', 'per_page', 'current_page', 'last_page'],
                ],
            ]);

        if ($message !== null) {
            $response->assertJsonPath('message', $message);
        }

        return $response;
    }

    // =========================================================================
    // Error responses
    // =========================================================================

    /**
     * Assert a 422 Unprocessable response with optional field-level error assertions.
     * Pass field names to verify they appear in the errors object.
     *
     *   $this->assertValidationError($response, 'email', 'name');
     */
    public function assertValidationError(TestResponse $response, string ...$fields): TestResponse
    {
        $response->assertUnprocessable()
            ->assertJsonStructure(['message', 'errors']);

        foreach ($fields as $field) {
            $response->assertJsonPath(
                "errors.{$field}",
                fn ($value) => is_array($value) && count($value) > 0,
            );
        }

        return $response;
    }

    /**
     * Assert a 404 Not Found response.
     */
    public function assertNotFoundResponse(TestResponse $response, ?string $message = null): TestResponse
    {
        $response->assertNotFound()
            ->assertJsonStructure(['message']);

        if ($message !== null) {
            $response->assertJsonPath('message', $message);
        }

        return $response;
    }

    /**
     * Assert a 401 Unauthorized response.
     */
    public function assertUnauthorizedResponse(TestResponse $response): TestResponse
    {
        return $response->assertUnauthorized()
            ->assertJsonStructure(['message']);
    }

    /**
     * Assert a 403 Forbidden response.
     */
    public function assertForbiddenResponse(TestResponse $response): TestResponse
    {
        return $response->assertForbidden()
            ->assertJsonStructure(['message']);
    }

    /**
     * Assert a fatal 500 response contains an exception_id UUID.
     * Use to verify Layer 3 exception handling (handleException()) fired.
     *
     *   $this->assertHasExceptionId($response);
     */
    public function assertHasExceptionId(TestResponse $response): TestResponse
    {
        $response->assertInternalServerError()
            ->assertJsonStructure(['message', 'exception_id'])
            ->assertJsonPath(
                'exception_id',
                fn ($value) => is_string($value) && preg_match(
                    '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
                    $value,
                ) === 1,
            );

        return $response;
    }

    // =========================================================================
    // Payload inspection
    // =========================================================================

    /**
     * Assert the response payload contains the expected subset.
     * Returns the response for further chaining.
     *
     *   $this->assertPayload($response, ['name' => 'John', 'role' => 'admin']);
     */
    public function assertPayload(TestResponse $response, array $subset): TestResponse
    {
        $response->assertJson(['payload' => $subset]);

        return $response;
    }

    /**
     * Assert a specific value at a dot-notation path within the payload.
     *
     *   $this->assertPayloadPath($response, 'user.email', 'john@example.com');
     *   $this->assertPayloadPath($response, 'orders.0.status', 'pending');
     */
    public function assertPayloadPath(TestResponse $response, string $path, mixed $expected): TestResponse
    {
        $response->assertJsonPath("payload.{$path}", $expected);

        return $response;
    }

    /**
     * Assert the payload array contains exactly N items.
     * Use for collection/index responses.
     *
     *   $this->assertPayloadCount($response, 3);
     */
    public function assertPayloadCount(TestResponse $response, int $count): TestResponse
    {
        $response->assertJsonCount($count, 'payload');

        return $response;
    }

    /**
     * Assert the paginated response reports a specific total record count.
     *
     *   $this->assertPaginationTotal($response, 42);
     */
    public function assertPaginationTotal(TestResponse $response, int $total): TestResponse
    {
        $response->assertJsonPath('meta.pagination.total', $total);

        return $response;
    }
}
