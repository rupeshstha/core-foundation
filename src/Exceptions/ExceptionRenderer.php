<?php

namespace CoreFoundation\Exceptions;

use Throwable;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Database\QueryException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;

/**
 * ExceptionRenderer
 *
 * Registers all global exception rendering for the API with Laravel's
 * withExceptions() pipeline.
 *
 * Called once from CoreFoundationServiceProvider::boot() — the app developer
 * never touches this directly. All framework exceptions are handled here,
 * producing consistent JSON responses across the entire API regardless of
 * whether individual controllers have try/catch blocks.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ EXCEPTION HIERARCHY (most specific → least specific)                        │
 * │                                                                             │
 * │  ValidationException          → 422  { message, errors: {...} }            │
 * │  ModelNotFoundException        → 404  { message, errors: {} }              │
 * │  NotFoundHttpException         → 404  { message, errors: {} }              │
 * │  MethodNotAllowedHttpException → 405  { message, errors: {} }              │
 * │  AuthenticationException       → 401  { message, errors: {} }              │
 * │  AuthorizationException        → 403  { message, errors: {} }              │
 * │  QueryException                → 400  { message, errors: {} }              │
 * │  HttpException (catch-all)     → uses exception's status code              │
 * │  Throwable (fatal fallback)    → 500  { message, errors: {}, exception_id }│
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ FATAL EXCEPTION ID                                                          │
 * │                                                                             │
 * │ Any exception not matched by a specific handler falls through to the        │
 * │ Throwable catch-all. A UUID is generated, attached to the log via           │
 * │ withContext(), and returned in the response as 'exception_id'.              │
 * │                                                                             │
 * │ Support teams grep logs for that UUID to find the exact stack trace.       │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
class ExceptionRenderer
{
    /**
     * Register all renderers with Laravel's exception pipeline.
     * Called from CoreFoundationServiceProvider::boot().
     *
     * @param  Exceptions  $exceptions  The withExceptions() configuration object
     */
    public static function register(Exceptions $exceptions): void
    {
        static::registerRenderers($exceptions);
        static::registerReporters($exceptions);
    }

    // =========================================================================
    // Renderers — control what the API client receives
    // =========================================================================

    private static function registerRenderers(Exceptions $exceptions): void
    {
        // ── ValidationException ───────────────────────────────────────────────
        $exceptions->render(function (ValidationException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null; // let Laravel handle non-JSON requests normally
            }

            return response()->json([
                'message' => $e->getMessage(),
                'errors' => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        // ── ModelNotFoundException ─────────────────────────────────────────────
        $exceptions->render(function (ModelNotFoundException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Record not found.',
            ], Response::HTTP_NOT_FOUND);
        });

        // ── NotFoundHttpException ──────────────────────────────────────────────
        $exceptions->render(function (NotFoundHttpException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Not found.',
            ], Response::HTTP_NOT_FOUND);
        });

        // ── MethodNotAllowedHttpException ──────────────────────────────────────
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Method not allowed.',
            ], Response::HTTP_METHOD_NOT_ALLOWED);
        });

        // ── AuthenticationException ────────────────────────────────────────────
        $exceptions->render(function (AuthenticationException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'Unauthenticated.',
            ], Response::HTTP_UNAUTHORIZED);
        });

        // ── AuthorizationException ─────────────────────────────────────────────
        $exceptions->render(function (AuthorizationException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => 'This action is unauthorized.',
            ], Response::HTTP_FORBIDDEN);
        });

        // ── QueryException ─────────────────────────────────────────────────────
        $exceptions->render(function (QueryException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => static::resolveQueryMessage($e),
            ], Response::HTTP_BAD_REQUEST);
        });

        // ── HttpException (catch-all for Symfony HTTP exceptions) ──────────────
        $exceptions->render(function (HttpException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage() ?: Response::$statusTexts[$e->getStatusCode()] ?? 'Error.',
            ], $e->getStatusCode());
        });

        // ── Fatal fallback — anything not matched above ────────────────────────
        // BaseApiException subclasses render themselves via their own render()
        // method so they never reach this fallback.
        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            // Skip — BaseApiException subclasses handle themselves
            if ($e instanceof BaseApiException) {
                return null;
            }

            $exceptionId = (string) Str::uuid();

            // Attach UUID to the log entry so support can correlate response → log
            logger()->withContext(['exception_id' => $exceptionId]);

            return response()->json([
                'message' => 'An unexpected error occurred. Please contact support with the exception ID.',
                'errors' => [],
                'exception_id' => $exceptionId,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        });
    }

    // =========================================================================
    // Reporters — control what gets logged and how
    // =========================================================================

    private static function registerReporters(Exceptions $exceptions): void
    {
        // QueryException — log with SQL context for easier debugging
        $exceptions->report(function (QueryException $e): void {
            logger()->error('QueryException', [
                'sql' => $e->getMessage(),
                'bindings' => $e->getBindings(),
                'url' => request()->fullUrl(),
                'method' => request()->method(),
            ]);
        })->stop(); // stop() prevents default logging so we don't double-log

        // All other exceptions use Laravel's default reporting pipeline.
        // BaseApiException subclasses that implement ShouldntReport are
        // automatically suppressed by Laravel — no extra code needed.
    }

    // =========================================================================
    // Internals
    // =========================================================================

    private static function resolveQueryMessage(QueryException $e): string
    {
        return match ($e->errorInfo[1] ?? null) {
            1062 => 'Duplicate entry.',
            1451 => 'Cannot delete or update a parent row: a foreign key constraint fails.',
            default => 'A database error occurred. Please try again later.',
        };
    }
}
