<?php

namespace CoreFoundation\Testing;

use Illuminate\Foundation\Testing\TestCase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use CoreFoundation\Testing\Concerns\AssertsApiResponse;

/**
 * BaseTestCase
 *
 * Abstract test case for CoreFoundation API applications.
 * Provides JSON API response envelope assertions and sensible HTTP test defaults.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ APPLICATION SETUP                                                           │
 * │                                                                             │
 * │ 1. Create tests/TestCase.php in your app:                                   │
 * │                                                                             │
 * │   abstract class TestCase extends BaseTestCase                              │
 * │   {                                                                         │
 * │       use CreatesApplication;                                               │
 * │       use RefreshDatabase;       // full reset — use for integration tests  │
 * │       // OR:                                                                │
 * │       use DatabaseTransactions;  // faster — use for feature/unit tests     │
 * │   }                                                                         │
 * │                                                                             │
 * │ 2. In tests/Pest.php, wire your TestCase per test directory:                │
 * │                                                                             │
 * │   uses(TestCase::class)->in('Feature', 'Unit');                             │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WRITING FEATURE TESTS (Pest)                                                │
 * │                                                                             │
 * │   it('fetches a user', function () {                                        │
 * │       $user = User::factory()->create();                                    │
 * │                                                                             │
 * │       $this->assertSuccessResponse(                                         │
 * │           $this->getJson("/api/users/{$user->id}")                          │
 * │       )->assertJsonPath('payload.email', $user->email);                     │
 * │   });                                                                       │
 * │                                                                             │
 * │   it('returns validation errors for missing fields', function () {          │
 * │       $this->assertValidationError(                                         │
 * │           $this->postJson('/api/users', []),                                │
 * │           'name', 'email',                                                  │
 * │       );                                                                    │
 * │   });                                                                       │
 * │                                                                             │
 * │   it('paginates results', function () {                                     │
 * │       User::factory(30)->create();                                          │
 * │                                                                             │
 * │       $this->assertPaginatedResponse(                                       │
 * │           $this->getJson('/api/users')                                      │
 * │       );                                                                    │
 * │   });                                                                       │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ AUTHENTICATION                                                              │
 * │                                                                             │
 * │ Use Laravel's native actingAs() — no custom wrapper needed:                 │
 * │                                                                             │
 * │   $user = User::factory()->create();                                        │
 * │   $this->actingAs($user)                                                    │
 * │        ->getJson('/api/me');                                                │
 * │                                                                             │
 * │ For token-based auth, override defaultHeaders():                            │
 * │                                                                             │
 * │   protected function defaultHeaders(): array                                │
 * │   {                                                                         │
 * │       return array_merge(parent::defaultHeaders(), [                        │
 * │           'Authorization' => 'Bearer ' . $this->token,                      │
 * │       ]);                                                                   │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ DATABASE STRATEGY                                                           │
 * │                                                                             │
 * │ BaseTestCase does NOT prescribe a database strategy — add to your           │
 * │ application's TestCase:                                                     │
 * │                                                                             │
 * │   use RefreshDatabase;      → full migration per test class (slower,        │
 * │                               required for tests that spawn new processes,  │
 * │                               queue workers, or observe model events)       │
 * │                                                                             │
 * │   use DatabaseTransactions; → wraps each test in a transaction and rolls    │
 * │                               back (faster, sufficient for most HTTP tests) │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
abstract class BaseTestCase extends TestCase
{
    use AssertsApiResponse;

    protected function setUp(): void
    {
        parent::setUp();

        // Rate limiting breaks test determinism — disable globally for tests.
        $this->withoutMiddleware(ThrottleRequests::class);

        // Apply default headers to every request in the test.
        $this->withHeaders($this->defaultHeaders());
    }

    /**
     * HTTP headers sent with every request in this test class.
     *
     * Override to add auth headers, API versioning, or tenant context.
     * Always merge with parent to preserve the Accept header.
     *
     *   protected function defaultHeaders(): array
     *   {
     *       return array_merge(parent::defaultHeaders(), [
     *           'Authorization' => 'Bearer ' . $this->token,
     *           'X-Tenant-Id'   => $this->tenant->id,
     *       ]);
     *   }
     */
    protected function defaultHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }
}
