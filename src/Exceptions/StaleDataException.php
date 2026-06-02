<?php

namespace CoreFoundation\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use Symfony\Component\HttpFoundation\Response;

/**
 * Thrown when an optimistic lock check fails during an update.
 * Silent — this is an expected business flow resolution (race condition).
 * Returns a 409 Conflict status.
 */
class StaleDataException extends BaseApiException implements ShouldntReport
{
    protected int $status = Response::HTTP_CONFLICT;

    protected $message = 'The record has been modified by another user. Please refresh and try again.';
}
