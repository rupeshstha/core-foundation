<?php

namespace CoreFoundation\Exceptions;

use Exception;
use Throwable;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * BaseApiException
 *
 * Base class for all domain exceptions in the application.
 * Extends PHP's Exception and plugs into Laravel's exception rendering pipeline
 * via the render() method — no handler registration needed.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ TWO TYPES OF DOMAIN EXCEPTIONS                                              │
 * │                                                                             │
 * │ SILENT (known, client-fixable, no log noise):                               │
 * │   Implement ShouldntReport. Laravel will render but never log them.         │
 * │                                                                             │
 * │   class InsufficientInventoryException extends BaseApiException             │
 * │       implements ShouldntReport                                             │
 * │   {                                                                         │
 * │       protected int    $status  = Response::HTTP_UNPROCESSABLE_ENTITY;      │
 * │       protected string $message = 'Insufficient inventory.';                │
 * │   }                                                                         │
 * │                                                                             │
 * │ REPORTABLE (unexpected, needs investigation, always logged):                │
 * │   Just extend BaseApiException — reported by default.                       │
 * │   Optionally override report() for custom channels (Slack, Sentry etc).    │
 * │                                                                             │
 * │   class PaymentGatewayTimeoutException extends BaseApiException             │
 * │   {                                                                         │
 * │       protected int    $status  = Response::HTTP_SERVICE_UNAVAILABLE;       │
 * │       protected string $message = 'Payment gateway timed out.';             │
 * │                                                                             │
 * │       public function report(): void                                        │
 * │       {                                                                     │
 * │           // Custom reporting e.g. Slack, PagerDuty, Sentry                 │
 * │           Log::critical($this->message, $this->context());                  │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ EXTRA ERRORS (for validation-style field errors on domain exceptions)       │
 * │                                                                             │
 * │   throw new OrderValidationException(                                       │
 * │       errors: ['quantity' => ['Must be at least 1.']]                       │
 * │   );                                                                        │
 * │                                                                             │
 * │   // Response:                                                              │
 * │   {                                                                         │
 * │     "message": "Order validation failed.",                                  │
 * │     "errors":  { "quantity": ["Must be at least 1."] }                      │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
abstract class BaseApiException extends Exception
{
    /**
     * HTTP status code for this exception.
     * Override in subclasses to set a specific status.
     */
    protected int $status = Response::HTTP_INTERNAL_SERVER_ERROR;

    /**
     * Field-level errors — mirrors ValidationException's errors bag.
     * Use for domain exceptions that carry structured field errors.
     *
     * @var array<string, array<string>>
     */
    protected array $errors = [];

    public function __construct(
        string $message = '',
        array $errors = [],
        ?int $status = null,
        ?Throwable $previous = null,
    ) {
        // Allow message and status override at throw time
        if ($message !== '') {
            $this->message = $message;
        }

        if ($status !== null) {
            $this->status = $status;
        }

        $this->errors = $errors;

        parent::__construct($this->message, $this->status, $previous);
    }

    /**
     * Render the exception as a JSON response.
     * Laravel calls this automatically — no handler registration needed.
     *
     * The response always uses the agreed API envelope:
     *   { "message": "...", "errors": { ... } }
     */
    public function render(Request $request): JsonResponse
    {
        return response()->json([
                'message' => $this->getMessage(),
                'errors' => $this->errors,
            ],
            $this->status,
        );
    }

    /**
     * Get exception context for log entries.
     * Laravel merges this into the log record automatically.
     *
     * Override to add domain-specific context:
     *
     *   public function context(): array
     *   {
     *       return array_merge(parent::context(), [
     *           'order_id' => $this->orderId,
     *       ]);
     *   }
     */
    public function context(): array
    {
        return [
            'exception' => static::class,
            'status' => $this->status,
        ];
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
