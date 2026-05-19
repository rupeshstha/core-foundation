<?php

/**
 * =============================================================================
 * DOMAIN EXCEPTION EXAMPLES
 * =============================================================================
 *
 * Shows both patterns — silent (ShouldntReport) and reportable exceptions.
 * Each exception lives in its own file at App\Exceptions\{Name}.php
 */

// =============================================================================
// PATTERN A — Silent exception (known, client-fixable, never logged)
// Use for: business rule violations the client can fix (out of stock,
// duplicate entry, insufficient balance, etc.)
// =============================================================================

namespace App\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\Exceptions\BaseApiException;

/**
 * Thrown when a requested product has insufficient inventory.
 * Silent — this is expected business flow, not an error worth logging.
 */
class InsufficientInventoryException extends BaseApiException implements ShouldntReport
{
    protected int $status = Response::HTTP_UNPROCESSABLE_ENTITY;

    protected string $message = 'Insufficient inventory for the requested quantity.';

    public static function forProduct(string $sku, int $requested, int $available): static
    {
        $instance = new static(errors: [
            'quantity' => [
                "Only {$available} units of {$sku} are available, {$requested} requested.",
            ],
        ]);

        return $instance;
    }
}

// Usage:
//   throw InsufficientInventoryException::forProduct('SKU-001', 10, 3);
//
// Response (422):
//   {
//     "message": "Insufficient inventory for the requested quantity.",
//     "errors":  { "quantity": ["Only 3 units of SKU-001 are available, 10 requested."] }
//   }
//
// Log: nothing — ShouldntReport suppresses all logging.

// =============================================================================
// PATTERN B — Reportable exception (unexpected, needs investigation, logged)
// Use for: third-party failures, infrastructure errors, anything ops should
// know about (payment gateway down, external API timeout, etc.)
// =============================================================================

/**
 * Thrown when the payment gateway returns an unexpected failure.
 * Reportable — ops need to know, logged with full context.
 */
class PaymentGatewayException extends BaseApiException
{
    protected int $status = Response::HTTP_SERVICE_UNAVAILABLE;

    protected string $message = 'Payment gateway is currently unavailable. Please try again shortly.';

    public function __construct(
        private readonly string $gatewayResponse,
        private readonly string $transactionId,
    ) {
        parent::__construct();
    }

    /**
     * Override context() to add domain-specific fields to the log entry.
     * Laravel merges this into every log record for this exception.
     */
    public function context(): array
    {
        return array_merge(parent::context(), [
            'gateway_response' => $this->gatewayResponse,
            'transaction_id' => $this->transactionId,
        ]);
    }
}

// Usage:
//   throw new PaymentGatewayException(
//       gatewayResponse: $gateway->lastResponse(),
//       transactionId:   $txId,
//   );
//
// Response (503):
//   {
//     "message": "Payment gateway is currently unavailable. Please try again shortly.",
//     "errors":  {}
//   }
//
// Log: error with full context — gateway_response, transaction_id, stack trace.

// =============================================================================
// CLEAN CONTROLLER — no try/catch needed for known exceptions
// =============================================================================

namespace App\Http\Controllers;

use Throwable;
use App\Services\OrderService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\StoreOrderRequest;
use App\Exceptions\InsufficientInventoryException;
use CoreFoundation\Http\Controllers\BaseController;

class OrderController extends BaseController
{
    public function __construct(
        private readonly OrderService $orderService,
    ) {}

    /**
     * Layer 1 handles: ValidationException (StoreOrderRequest fails)
     * Layer 2 handles: InsufficientInventoryException (thrown in service)
     * Layer 3 handles: anything unexpected → UUID + 500
     */
    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $result = $this->orderService->place($request->validated());
        } catch (Throwable $e) {
            return $this->handleException($e); // layer 3 safety net
        }

        return $this->createdResponse(
            message: $this->lang('create-success'),
            payload: $result->toArray(),
        );
    }
}

// =============================================================================
// EXCEPTION FLOW REFERENCE
// =============================================================================
//
// StoreOrderRequest validation fails
//   → ValidationException thrown by FormRequest
//   → Layer 1 (ExceptionRenderer) catches it
//   → 422 { message, errors: { field: [...] } }
//   → handleException() never called
//
// InsufficientInventoryException thrown in service
//   → Layer 2: BaseApiException::render() called by Laravel automatically
//   → 422 { message, errors: { quantity: [...] } }
//   → handleException() never called
//   → ShouldntReport: no log entry
//
// PaymentGatewayException thrown in service
//   → Layer 2: BaseApiException::render() called by Laravel automatically
//   → 503 { message, errors: {} }
//   → handleException() never called
//   → Reported: log error with context (gateway_response, transaction_id)
//
// Unexpected NullPointerException / TypeError / etc.
//   → Falls through to handleException() in catch (Throwable $e)
//   → UUID generated, logged, 500 returned
//   → { message: "...", errors: {}, exception_id: "uuid" }
