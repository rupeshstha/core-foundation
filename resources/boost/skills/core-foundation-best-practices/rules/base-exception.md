# BaseApiException Best Practices

## Three Layers — Never Collapse

```
Layer 1: ExceptionRenderer (ServiceProvider)  — all framework exceptions, globally, every route
Layer 2: BaseApiException::render()            — domain exceptions, self-rendering
Layer 3: handleException() on BaseController   — last resort, UUID + 500
```

Each layer has a specific job. Collapsing them (catching framework exceptions manually, or mapping domain exceptions in controllers) defeats the architecture.

## Layer 2 — Silent Domain Exception

Use `ShouldntReport` for expected client-fixable errors. These produce no log entry and no UUID.

```php
use Illuminate\Contracts\Debug\ShouldntReport;
use CoreFoundation\Exceptions\BaseApiException;

class OrderNotFoundException extends BaseApiException implements ShouldntReport
{
    public function __construct(int $id)
    {
        parent::__construct("Order {$id} not found.", 404);
    }
}
```

## Layer 2 — Reportable Domain Exception

Without `ShouldntReport`, the exception is logged but still renders its own JSON response:

```php
class PaymentGatewayException extends BaseApiException
{
    public function context(): array
    {
        return ['gateway' => $this->gateway, 'transaction_id' => $this->transactionId];
    }
}
```

## UUID Only in Layer 3

`exception_id` appears in the response only from `handleException()` (Layer 3). Never generate a UUID for a known domain exception.

Incorrect:
```php
class OrderNotFoundException extends BaseApiException
{
    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'exception_id' => Str::uuid(),  // wrong — not a fatal
        ], 404);
    }
}
```

Correct: inherit the default `render()` from `BaseApiException` — it omits `exception_id` automatically.
