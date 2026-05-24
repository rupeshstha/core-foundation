# BaseTestCase Rules

## Application Setup — Two Files

**`tests/TestCase.php`** — your app's abstract test case:
```php
abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use RefreshDatabase;    // for integration tests
    // OR:
    use DatabaseTransactions; // for feature/unit tests (faster)
}
```

**`tests/Pest.php`** — wire the TestCase per directory:
```php
uses(TestCase::class)->in('Feature', 'Unit');
```

## Database Strategy — Choose One Per TestCase

| Strategy | When to use |
|---|---|
| `RefreshDatabase` | Full migration per test class. Required when tests spawn queue workers, observe model events, or create new processes. Slower. |
| `DatabaseTransactions` | Wraps each test in a transaction and rolls back. Faster. Sufficient for most HTTP feature tests. |

`BaseTestCase` does not prescribe a strategy — add the trait to your app's `TestCase`.

## Assertion Methods — Use the Envelope Helpers

All assertion helpers accept a `TestResponse` and return it for chaining.

### Success responses

```php
// 200 OK with { message, payload }
$this->assertSuccessResponse($response)
     ->assertJsonPath('payload.email', $user->email);

// Assert exact message:
$this->assertSuccessResponse($response, 'User fetched.');

// 201 Created with { message, payload }
$this->assertCreatedResponse($response)
     ->assertJsonPath('payload.id', $user->id);

// 204 No Content
$this->assertNoContentResponse($response);

// 200 paginated with { message, payload, meta.pagination }
$this->assertPaginatedResponse($response)
     ->assertJsonPath('meta.pagination.total', 42);
```

### Error responses

```php
// 422 with { message, errors } — pass field names to verify they have errors
$this->assertValidationError($response, 'email', 'name');

// 404
$this->assertNotFoundResponse($response);

// 401
$this->assertUnauthorizedResponse($response);

// 403
$this->assertForbiddenResponse($response);

// 500 with exception_id UUID (Layer 3 fatal fallback)
$this->assertHasExceptionId($response);
```

### Payload inspection

```php
// Assert payload subset
$this->assertPayload($response, ['name' => 'John', 'role' => 'admin']);

// Assert dot-notation path inside payload
$this->assertPayloadPath($response, 'user.email', 'john@example.com');
$this->assertPayloadPath($response, 'orders.0.status', 'pending');

// Assert payload item count (for collection responses)
$this->assertPayloadCount($response, 3);

// Assert pagination total
$this->assertPaginationTotal($response, 42);
```

## Key: Use `payload`, Not `data`

The envelope uses `payload`, not `data`. Always use `assertJsonPath('payload.field', ...)` — never `data.field`.

Incorrect:
```php
$response->assertJsonPath('data.email', $user->email);
```

Correct:
```php
$this->assertSuccessResponse($response)
     ->assertJsonPath('payload.email', $user->email);
```

## Authentication — Use `actingAs()`, No Custom Wrapper

```php
$user = User::factory()->create();

$this->actingAs($user)
     ->getJson("/api/users/{$user->id}");
```

For token-based auth, override `defaultHeaders()` in your app's TestCase — not in individual tests:

```php
protected function defaultHeaders(): array
{
    return array_merge(parent::defaultHeaders(), [
        'Authorization' => 'Bearer ' . $this->token,
        'X-Tenant-Id'   => $this->tenant->id,
    ]);
}
```

Always call `parent::defaultHeaders()` — the base sets `Accept: application/json` which ensures JSON responses throughout.

## Rate Limiting Is Disabled Automatically

`BaseTestCase::setUp()` calls `$this->withoutMiddleware(ThrottleRequests::class)` — rate limiting is disabled for all tests automatically. Do not add it manually.

## Writing Feature Tests (Pest)

```php
it('fetches a user', function () {
    $user = User::factory()->create();

    $this->assertSuccessResponse(
        $this->getJson("/api/users/{$user->id}")
    )->assertJsonPath('payload.email', $user->email);
});

it('returns validation errors for missing fields', function () {
    $this->assertValidationError(
        $this->postJson('/api/users', []),
        'name', 'email',
    );
});

it('paginates results', function () {
    User::factory(30)->create();

    $this->assertPaginatedResponse(
        $this->getJson('/api/users')
    );
});

it('returns 403 for unauthorized action', function () {
    $user  = User::factory()->create();
    $other = User::factory()->create();

    $this->assertForbiddenResponse(
        $this->actingAs($user)->deleteJson("/api/orders/{$other->order->id}")
    );
});
```
