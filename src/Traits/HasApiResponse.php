<?php

namespace CoreFoundation\Traits;

use Illuminate\Http\JsonResponse;
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Pagination\AbstractPaginator;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * HasApiResponse
 *
 * Standardised JSON response helpers for API controllers.
 *
 * All responses follow the agreed envelope:
 *
 *   Success:
 *   {
 *     "message": "Users fetched successfully.",
 *     "payload": { ... } | [ ... ],
 *     "meta":    { "pagination": { ... } }   ← only when paginated
 *   }
 *
 *   Error:
 *   {
 *     "message":      "Something went wrong.",
 *     "errors":       { ... }                ← validation errors or empty {}
 *     "exception_id": "uuid"                 ← only on fatal (5xx) errors
 *   }
 *
 * The response shape methods (successEnvelope, errorEnvelope) are protected
 * and overridable so teams can adjust the envelope without touching the helpers.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE                                                                       │
 * │                                                                             │
 * │ $message is always a translation key resolved via $this->lang() — see       │
 * │ HasLang — never a hardcoded string.                                         │
 * │                                                                             │
 * │   return $this->successResponse(                                            │
 * │       message: $this->lang('fetch-success'),                                │
 * │       payload: UserResource::collection($users),                            │
 * │   );                                                                        │
 * │                                                                             │
 * │   return $this->paginatedResponse(                                          │
 * │       paginator: $users,                                                    │
 * │       message: $this->lang('fetch-success'),                                │
 * │   );                                                                        │
 * │                                                                             │
 * │   return $this->createdResponse(                                            │
 * │       message: $this->lang('create-success'),                               │
 * │       payload: new UserResource($user),                                     │
 * │   );                                                                        │
 * │                                                                             │
 * │   return $this->noContentResponse();                                        │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait HasApiResponse
{
    /**
     * 200 OK — generic success with a payload.
     */
    final protected function successResponse(
        ?string $message = null,
        mixed $payload = null,
        int $status = Response::HTTP_OK,
    ): JsonResponse {
        return response()->json(
            data: $this->successEnvelope($message, $payload),
            status: $status,
        );
    }

    /**
     * 201 Created — resource was successfully created.
     */
    final protected function createdResponse(
        ?string $message = null,
        mixed $payload = null,
    ): JsonResponse {
        return $this->successResponse($message, $payload, Response::HTTP_CREATED);
    }

    /**
     * 204 No Content — success with no body (delete, detach, etc).
     */
    final protected function noContentResponse(): JsonResponse
    {
        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * 200 OK — paginated collection with meta.pagination block.
     *
     * Accepts either a raw paginator (items come out as plain arrays) or a
     * ResourceCollection wrapping a paginator (items are resource-transformed).
     * Appends all current query parameters to pagination links automatically.
     *
     * Usage:
     *   return $this->paginatedResponse(new UserCollection($users));  // with resource
     *   return $this->paginatedResponse($users);                      // raw items
     */
    final protected function paginatedResponse(
        AbstractPaginator|ResourceCollection $paginator,
        ?string $message = null,
    ): JsonResponse {
        $rawPaginator = $paginator instanceof ResourceCollection
            ? $paginator->resource
            : $paginator;

        $rawPaginator->appends(request()->query());

        $payload = $paginator instanceof ResourceCollection
            ? $paginator
            : $paginator->items();

        return response()->json(
            $this->successEnvelope($message, $payload, $this->paginationMeta($rawPaginator))
        );
    }

    /**
     * Build an error JsonResponse.
     * Called by HasExceptionHandler::handleException() — not typically called directly.
     */
    final protected function errorResponse(
        ?string $message = null,
        int $status = Response::HTTP_INTERNAL_SERVER_ERROR,
        array $errors = [],
        ?string $exceptionId = null,
    ): JsonResponse {
        return response()->json(
            data: $this->errorEnvelope($message, $errors, $exceptionId),
            status: $status,
        );
    }

    /**
     * Build the success response envelope.
     * Override to add/rename top-level keys for your team's convention.
     */
    protected function successEnvelope(?string $message = null, mixed $payload = [], ?array $meta = null): array
    {
        $envelope = [
            'message' => $message,
            'payload' => $this->resolvePayload($payload),
        ];

        if ($meta !== null) {
            $envelope['meta'] = $meta;
        }

        return $envelope;
    }

    /**
     * Build the error response envelope.
     * Override to add/rename top-level keys for your team's convention.
     */
    protected function errorEnvelope(?string $message = null, array $errors = [], ?string $exceptionId = null): array
    {
        $envelope = [
            'message' => $message,
            'errors' => $errors,
        ];

        if ($exceptionId !== null) {
            $envelope['exception_id'] = $exceptionId;
        }

        return $envelope;
    }

    /**
     * Resolve the payload to a plain array.
     * Handles JsonResources, Collections, Arrayables, and plain arrays.
     */
    private function resolvePayload(mixed $payload): mixed
    {
        if ($payload === null) {
            return null;
        }

        if ($payload instanceof JsonResource) {
            return $payload->resolve();
        }

        if ($payload instanceof Arrayable) {
            return $payload->toArray();
        }

        return $payload;
    }

    private function paginationMeta(AbstractPaginator $paginator): array
    {
        $meta = [
            'current_page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'from' => $paginator->firstItem(),
            'to' => $paginator->lastItem(),
            'path' => $paginator->path(),
            'next_page_url' => $paginator->nextPageUrl(),
            'prev_page_url' => $paginator->previousPageUrl(),
        ];

        // LengthAwarePaginator has total/last_page — CursorPaginator does not
        if (method_exists($paginator, 'total')) {
            $meta['total'] = $paginator->total();
            $meta['last_page'] = $paginator->lastPage();
        }

        return ['pagination' => $meta];
    }
}
