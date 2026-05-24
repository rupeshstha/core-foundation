# BaseApiException Rules

## Two Types of Domain Exceptions

```php
use CoreFoundation\Exceptions\BaseApiException;
use Illuminate\Contracts\Debug\ShouldntReport;
use Symfony\Component\HttpFoundation\Response;

// Silent — client-fixable, never logged
class InsufficientInventoryException extends BaseApiException
    implements ShouldntReport
{
    protected int    $status  = Response::HTTP_UNPROCESSABLE_ENTITY;
    protected string $message = 'Insufficient inventory.';
}

// Reportable — unexpected, always logged
class PaymentGatewayTimeoutException extends BaseApiException
{
    protected int    $status  = Response::HTTP_SERVICE_UNAVAILABLE;
    protected string $message = 'Payment gateway timed out.';
}
```

Implement `ShouldntReport` when the exception is expected and client-fixable. Omit it when the exception signals a system failure that needs investigation.

## Set Status and Message as Properties, Not in Constructor

Override the protected properties — do not pass values to the constructor at the throw site unless you need a one-off message override.

Incorrect:
```php
throw new OrderException('Order failed.', [], 422);
```

Correct (declare on the class):
```php
class OrderException extends BaseApiException
{
    protected int    $status  = Response::HTTP_UNPROCESSABLE_ENTITY;
    protected string $message = 'Order validation failed.';
}

// Throw without arguments:
throw new OrderException;

// Or with a one-off override:
throw new OrderException('Custom message for this specific case.');
```

## Field-Level Errors

Use the `errors` constructor argument to attach structured field errors — mirrors `ValidationException`:

```php
throw new OrderValidationException(
    errors: ['quantity' => ['Must be at least 1.']],
);

// Response:
// {
//   "message": "Order validation failed.",
//   "errors": { "quantity": ["Must be at least 1."] }
// }
```

## Enrich Log Context — Override `context()`, Not `report()`

`context()` is merged into the log entry automatically. Use it to add domain fields.

Incorrect:
```php
public function report(): void
{
    Log::error('Order failed', ['order_id' => $this->orderId]);
}
```

Correct:
```php
public function context(): array
{
    return array_merge(parent::context(), [
        'order_id' => $this->orderId,
    ]);
}
```

Override `report()` only when you need a non-default reporting channel (Slack, Sentry, PagerDuty).

## `render()` Is Already Handled — Never Override It

`BaseApiException::render()` produces the standard envelope automatically:

```json
{ "message": "...", "errors": {} }
```

Overriding `render()` breaks the envelope contract. Do not override it.

## `exception_id` — Only on Fatal Fallback (Layer 3)

`exception_id` (a UUID) appears only in 500 responses produced by the `ExceptionRenderer` fatal fallback — i.e., for `Throwable` exceptions that are not `BaseApiException` subclasses and not matched by any specific renderer.

`BaseApiException` subclasses render themselves and never receive an `exception_id`. Do not add one manually.

## Framework Exceptions Are Handled Automatically

The `ExceptionRenderer` (registered in `CoreFoundationServiceProvider`) already handles these — do not catch or re-render them in controllers:

| Exception | Status |
|---|---|
| `ValidationException` | 422 |
| `ModelNotFoundException` | 404 |
| `NotFoundHttpException` | 404 |
| `MethodNotAllowedHttpException` | 405 |
| `AuthenticationException` | 401 |
| `AuthorizationException` | 403 |
| `QueryException` | 400 |
| `HttpException` | exception's status |
| `Throwable` (fatal) | 500 + `exception_id` |

## Do Not Catch Exceptions in Controllers Unnecessarily

`BaseController::handleException()` exists for the cases where you must catch — delegate to it, never log directly.

Incorrect:
```php
try {
    $this->service->place($data);
} catch (Throwable $e) {
    Log::error('Failed', ['error' => $e->getMessage()]);
    return response()->json(['message' => 'Error'], 500);
}
```

Correct — let the exception bubble:
```php
$result = $this->service->place($data);
return $this->createdResponse('Order placed.', new OrderResource($result));
```

Or delegate to `handleException()` only when you need to add context to a caught exception:
```php
try {
    $this->externalApi->charge($order);
} catch (Throwable $e) {
    $this->handleException($e); // logs with context, re-throws or renders
}
```
