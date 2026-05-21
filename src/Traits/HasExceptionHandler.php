<?php

namespace CoreFoundation\Traits;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use CoreFoundation\Exceptions\BaseApiException;

/**
 * HasExceptionHandler
 *
 * Controller-level last-resort exception handler.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ THIS IS LAYER 3 — understand the full stack before using it                 │
 * │                                                                             │
 * │ Layer 1 — Global (ExceptionRenderer via ServiceProvider)                    │
 * │           Handles ALL framework exceptions (Validation, ModelNotFound,      │
 * │           Auth, etc.) for every JSON request automatically.                 │
 * │           You do not need try/catch for these in controllers.               │
 * │                                                                             │
 * │ Layer 2 — Domain (BaseApiException subclasses)                              │
 * │           Domain exceptions carry their own render() + report() logic.      │
 * │           Laravel calls them automatically when thrown anywhere.            │
 * │           Silent exceptions implement ShouldntReport — no log noise.        │
 * │                                                                             │
 * │ Layer 3 — This trait (handleException)                                      │
 * │           A safety net for third-party exceptions, unexpected throwables,   │
 * │           or any exception that slipped past Layers 1 and 2.               │
 * │           Generates a UUID, logs with context, returns 500.                 │
 * │                                                                             │
 * │ GUIDELINE:                                                                  │
 * │   Catch only the exceptions you genuinely need controller-level context     │
 * │   for. Let Layers 1 and 2 handle the rest.                                  │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE                                                                       │
 * │                                                                             │
 * │   public function store(StoreOrderRequest $request): JsonResponse           │
 * │   {                                                                         │
 * │       try {                                                                 │
 * │           $result = $this->orderService->place($request->validated());      │
 * │       } catch (Throwable $e) {                                              │
 * │           return $this->handleException($e);                                │
 * │       }                                                                     │
 * │                                                                             │
 * │       return $this->createdResponse($this->lang('create-success'), $result);│
 * │   }                                                                         │
 * │                                                                             │
 * │ For known domain exceptions you want to handle inline:                      │
 * │                                                                             │
 * │   } catch (InsufficientInventoryException $e) {                             │
 * │       // custom controller-level handling                                   │
 * │   } catch (Throwable $e) {                                                  │
 * │       return $this->handleException($e);  // everything else                │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait HasExceptionHandler
{
    /**
     * Last-resort exception handler for controller catch blocks.
     *
     * Decision tree:
     *  - BaseApiException  → delegate to its own render() — consistent envelope
     *  - Everything else   → fatal: UUID + log + 500
     *
     * Never re-throws. Always returns a JsonResponse.
     */
    final public function handleException(Throwable $exception): JsonResponse
    {
        // BaseApiException subclasses carry their own render logic.
        // Delegate to it so the response is consistent with global rendering.
        if ($exception instanceof BaseApiException) {
            return $exception->render(request());
        }

        // Fatal — unknown exception, generate UUID for support correlation
        $exceptionId = (string) Str::uuid();

        report($exception);

        logger()->error('Fatal exception: ' . $exception->getMessage(), [
            'exception_id' => $exceptionId,
            'exception' => get_class($exception),
            'message' => $exception->getMessage(),
            'url' => request()->fullUrl(),
            'method' => request()->method(),
            'user_id' => auth()->id(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
        ]);

        return response()->json([
            'message' => 'An unexpected error occurred. Please contact support with the exception ID.',
            'exception_id' => $exceptionId,
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }
}
