# BaseTestCase Best Practices

## Use Envelope Assertion Helpers

Never assert raw JSON structure — use the envelope helpers. They check the correct keys, status codes, and payload shapes in one call.

Incorrect:
```php
$response->assertStatus(200);
$response->assertJsonStructure(['message', 'payload']);
$response->assertJson(['payload' => ['id' => 1]]);
```

Correct:
```php
$this->assertSuccessResponse($response, ['id' => 1]);           // 200, checks payload
$this->assertCreatedResponse($response, ['id' => 1]);           // 201, checks payload
$this->assertValidationError($response, ['email']);              // 422, checks errors key
$this->assertPaginatedResponse($response);                      // 200, checks meta.pagination
```

## Payload Key Is `payload` — Not `data`

The CoreFoundation envelope uses `payload`. Laravel's default JSON resource uses `data`. They are not the same.

Incorrect:
```php
$response->assertJson(['data' => ['id' => 1]]);
```

Correct:
```php
$response->assertJson(['payload' => ['id' => 1]]);
// Or use the helper: $this->assertSuccessResponse($response, ['id' => 1]);
```

## Always `array_merge(parent::defaultHeaders(), ...)` When Adding Headers

Replacing headers drops defaults (Accept, Content-Type) that the test suite expects on every request.

Incorrect:
```php
protected function defaultHeaders(): array
{
    return ['X-Tenant-Id' => $this->tenant->id];  // drops Accept and Content-Type
}
```

Correct:
```php
protected function defaultHeaders(): array
{
    return array_merge(parent::defaultHeaders(), [
        'X-Tenant-Id' => $this->tenant->id,
    ]);
}
```

## `RefreshDatabase` vs. `DatabaseTransactions`

- `DatabaseTransactions` — wraps each test in a transaction and rolls back. Faster. Does not fire observers or queue jobs (they commit on job dispatch).
- `RefreshDatabase` — migrates fresh and seeds. Required for tests that fire observers, listeners, or queued jobs.

Use `DatabaseTransactions` by default. Switch to `RefreshDatabase` only when the test specifically exercises async side effects.
