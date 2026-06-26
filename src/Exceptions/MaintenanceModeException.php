<?php

namespace CoreFoundation\Exceptions;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * MaintenanceModeException
 *
 * Thrown when the system (or a specific scope) is under maintenance.
 * Renders a consistent 503 Service Unavailable envelope.
 */
class MaintenanceModeException extends BaseApiException
{
    protected int $status = Response::HTTP_SERVICE_UNAVAILABLE;

    public function __construct(
        string $message = 'Service Under Maintenance',
        protected ?int $retryAfter = null,
        protected ?string $reason = null
    ) {
        parent::__construct($message);
    }

    /**
     * Get the retry after time in seconds.
     */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }

    /**
     * Get the reason for maintenance.
     */
    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function render(Request $request): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'payload' => null,
            'meta' => array_filter([
                'retry_after' => $this->retryAfter,
                'reason' => $this->reason,
            ]),
        ], $this->status, [
            'Retry-After' => $this->retryAfter,
        ]);
    }
}
