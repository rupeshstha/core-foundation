<?php

namespace CoreFoundation\Exceptions;

use WeakMap;
use Throwable;
use Illuminate\Support\Str;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use CoreFoundation\Support\Lang;
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
 * Registers all global exception rendering and reporting for the API with
 * Laravel's withExceptions() pipeline.
 *
 * Called once from CoreFoundationServiceProvider::boot().
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ EXCEPTION HIERARCHY (most specific → least specific)                        │
 * │                                                                             │
 * │  ValidationException          → 422  { message, errors }                   │
 * │  ModelNotFoundException        → 404  { message }                          │
 * │  NotFoundHttpException         → 404  { message }                          │
 * │  MethodNotAllowedHttpException → 405  { message }                          │
 * │  AuthenticationException       → 401  { message }                          │
 * │  AuthorizationException        → 403  { message }                          │
 * │  QueryException                → 400  { message }          ← logged        │
 * │  HttpException (catch-all)     → uses exception's status                   │
 * │  Throwable (fatal fallback)    → 500  { message, exception_id } ← logged  │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ REPORTER → RENDERER UUID HANDOFF                                            │
 * │                                                                             │
 * │ Laravel always calls reporters before renderers. The fatal Throwable        │
 * │ reporter generates the UUID and stores it in a WeakMap keyed by the         │
 * │ exception object. The renderer retrieves and consumes it from there.        │
 * │                                                                             │
 * │ WeakMap is Octane-safe: entries are garbage-collected automatically         │
 * │ when the exception object is collected — no bleed between requests.         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
class ExceptionRenderer
{
    /**
     * Request body keys replaced with '[REDACTED]' before logging.
     * Only top-level keys are checked.
     */
    private const REDACTED_FIELDS = [
        'password', 'password_confirmation', 'current_password',
        'token', 'api_token', 'api_key',
        'secret', 'secret_key',
        'credit_card', 'card_number', 'cvv', 'cvc',
    ];

    /** Maximum stack frames written to the log. */
    private const TRACE_DEPTH = 20;

    /**
     * WeakMap<Throwable, string> — reporter stores UUID here, renderer reads it.
     * Lazily initialised via exceptionIds().
     */
    private static ?WeakMap $exceptionIds = null;

    /**
     * Register all renderers and reporters with Laravel's exception pipeline.
     */
    public static function register(Exceptions $exceptions): void
    {
        static::registerRenderers($exceptions);
        static::registerReporters($exceptions);
    }

    // =========================================================================
    // Renderers — what the API client receives
    // =========================================================================

    private static function registerRenderers(Exceptions $exceptions): void
    {
        // ── ValidationException ───────────────────────────────────────────────
        $exceptions->render(function (ValidationException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage(),
                'errors'  => $e->errors(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        });

        // ── NotFoundHttpException (covers ModelNotFoundException too) ──────────
        // Laravel's prepareException() converts ModelNotFoundException to
        // NotFoundHttpException before renderers run — check getPrevious() to
        // distinguish an Eloquent model miss from a generic 404.
        $exceptions->render(function (NotFoundHttpException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            $message = $e->getPrevious() instanceof ModelNotFoundException
                ? Lang::get('core-foundation::http.not-found-record')
                : Lang::get('core-foundation::http.not-found');

            return response()->json([
                'message' => $message,
                'errors'  => [],
            ], Response::HTTP_NOT_FOUND);
        });

        // ── MethodNotAllowedHttpException ──────────────────────────────────────
        $exceptions->render(function (MethodNotAllowedHttpException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => Lang::get('core-foundation::http.method-not-allowed'),
                'errors'  => [],
            ], Response::HTTP_METHOD_NOT_ALLOWED);
        });

        // ── AuthenticationException ────────────────────────────────────────────
        $exceptions->render(function (AuthenticationException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => Lang::get('core-foundation::http.unauthenticated'),
                'errors'  => [],
            ], Response::HTTP_UNAUTHORIZED);
        });

        // ── AuthorizationException ─────────────────────────────────────────────
        $exceptions->render(function (AuthorizationException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => Lang::get('core-foundation::http.unauthorized'),
                'errors'  => [],
            ], Response::HTTP_FORBIDDEN);
        });

        // ── QueryException ─────────────────────────────────────────────────────
        $exceptions->render(function (QueryException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => static::resolveQueryMessage($e),
                'errors'  => [],
            ], Response::HTTP_BAD_REQUEST);
        });

        // ── HttpException (catch-all for Symfony HTTP exceptions) ──────────────
        $exceptions->render(function (HttpException $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson()) {
                return null;
            }

            return response()->json([
                'message' => $e->getMessage() ?: Response::$statusTexts[$e->getStatusCode()] ?? 'Error.',
                'errors'  => [],
            ], $e->getStatusCode());
        });

        // ── Fatal fallback ─────────────────────────────────────────────────────
        // BaseApiException subclasses render themselves via render() and never
        // reach here. The UUID was generated by the reporter — retrieve it here
        // so the response and log entry share the same correlation ID.
        $exceptions->render(function (Throwable $e, Request $request): ?JsonResponse {
            if (! $request->expectsJson() || $e instanceof BaseApiException) {
                return null;
            }

            $ids         = static::exceptionIds();
            $exceptionId = isset($ids[$e]) ? $ids[$e] : (string) Str::uuid();
            unset($ids[$e]); // consume — no need to hold after response is built

            return response()->json([
                'message'      => Lang::get('core-foundation::http.server-error'),
                'errors'       => [],
                'exception_id' => $exceptionId,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        });
    }

    // =========================================================================
    // Reporters — what gets logged and how
    // =========================================================================

    private static function registerReporters(Exceptions $exceptions): void
    {
        // ── QueryException ─────────────────────────────────────────────────────
        // Registered first. Returning false stops the reporter chain so the
        // generic Throwable reporter below never runs for QueryExceptions,
        // and Laravel's default reporter is also suppressed.
        $exceptions->report(function (QueryException $e): false {
            logger()->error('QueryException.', [
                'sql'       => $e->getSql(),
                'bindings'  => $e->getBindings(),
                'exception' => static::buildExceptionContext($e),
                'request'   => static::resolveRequestContext(),
            ]);

            return false;
        });

        // ── Unhandled Throwable ────────────────────────────────────────────────
        // BaseApiException: skipped — ShouldntReport suppresses it before
        // reporters run, or Laravel's default handles non-silent ones.
        // QueryException: already handled above; returning false from that
        // reporter stops the chain before this runs for QueryExceptions.
        $exceptions->report(function (Throwable $e): mixed {
            if ($e instanceof BaseApiException || $e instanceof QueryException) {
                return null; // don't interfere — let their own path handle them
            }

            $uuid = (string) Str::uuid();
            static::exceptionIds()[$e] = $uuid; // renderer picks this up

            logger()->error('Unhandled exception.', [
                'exception_id' => $uuid,
                'exception'    => static::buildExceptionContext($e),
                'request'      => static::resolveRequestContext(),
            ]);

            return false; // stop Laravel's default reporter from double-logging
        });
    }

    // =========================================================================
    // Context builders
    // =========================================================================

    /**
     * Everything needed to replay the request:
     * method + url + route → curl / Postman reproduction
     * user_id              → impersonate or check permissions
     * body (redacted)      → exact payload that triggered the error
     */
    private static function buildRequestContext(Request $request): array
    {
        $params = array_map(
            static fn ($v) => is_object($v) && method_exists($v, 'getKey') ? $v->getKey() : $v,
            $request->route()?->parameters() ?? [],
        );

        return [
            'method'  => $request->method(),
            'url'     => $request->fullUrl(),
            'route'   => $request->route()?->getName(),
            'params'  => $params,
            'ip'      => $request->ip(),
            'user_id' => $request->user()?->getAuthIdentifier(),
            'body'    => static::redactSensitiveFields(
                $request->isJson()
                    ? ($request->json()->all() ?? [])
                    : $request->except(['_token']),
            ),
        ];
    }

    /**
     * Class, origin file:line, trimmed trace, and the immediate cause chain.
     *
     * Public so other package classes (BaseJob, etc.) can build consistent
     * exception context without reimplementing the same logic.
     */
    public static function buildExceptionContext(Throwable $e): array
    {
        $base = base_path() . DIRECTORY_SEPARATOR;

        $context = [
            'class'   => $e::class,
            'message' => $e->getMessage(),
            'file'    => str_replace($base, '', $e->getFile()),
            'line'    => $e->getLine(),
            'trace'   => static::formatTrace($e),
        ];

        if ($previous = $e->getPrevious()) {
            $context['caused_by'] = [
                'class'   => $previous::class,
                'message' => $previous->getMessage(),
                'file'    => str_replace($base, '', $previous->getFile()),
                'line'    => $previous->getLine(),
            ];
        }

        return $context;
    }

    /**
     * Stack frames as { at: "file.php:line", call: "Class->method()" }.
     * Paths are relative to the project root — IDE-navigable in most log viewers.
     * Limited to TRACE_DEPTH frames to keep log payloads manageable.
     */
    private static function formatTrace(Throwable $e): array
    {
        $base = base_path() . DIRECTORY_SEPARATOR;

        return array_slice(
            array_map(static function (array $frame) use ($base): array {
                $at = isset($frame['file'])
                    ? str_replace($base, '', $frame['file']) . ':' . ($frame['line'] ?? '?')
                    : null;

                $call = match (true) {
                    isset($frame['class'], $frame['type']) => "{$frame['class']}{$frame['type']}{$frame['function']}()",
                    isset($frame['function'])              => "{$frame['function']}()",
                    default                                => '{closure}',
                };

                return array_filter(
                    ['at' => $at, 'call' => $call],
                    static fn ($v) => $v !== null,
                );
            }, $e->getTrace()),
            0,
            static::TRACE_DEPTH,
        );
    }

    /** Replace values at known-sensitive keys with '[REDACTED]'. */
    private static function redactSensitiveFields(array $data): array
    {
        foreach (static::REDACTED_FIELDS as $field) {
            if (array_key_exists($field, $data)) {
                $data[$field] = '[REDACTED]';
            }
        }

        return $data;
    }

    /**
     * Returns console context in CLI where no HTTP request exists.
     */
    private static function resolveRequestContext(): array
    {
        if (app()->runningInConsole()) {
            return ['context' => 'console'];
        }

        return static::buildRequestContext(request());
    }

    /**
     * Lazily-initialised WeakMap for the reporter→renderer UUID handoff.
     *
     * WeakMap holds weak references — entries are garbage-collected automatically
     * when the exception object is collected, so no manual cleanup is needed and
     * there is no state bleed across Octane requests.
     */
    private static function exceptionIds(): WeakMap
    {
        return static::$exceptionIds ??= new WeakMap();
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Retrieve and consume the UUID generated by the reporter for this exception.
     *
     * Called by HasExceptionHandler when an exception is caught at the controller
     * level — the reporter has already logged the structured entry and stored the
     * UUID; this hands it to the response so log and client share the same ID.
     *
     * Returns a fresh UUID if the reporter did not run (e.g., the exception was
     * a BaseApiException or the reporter pipeline was bypassed).
     */
    public static function consumeExceptionId(Throwable $e): string
    {
        $ids         = static::exceptionIds();
        $exceptionId = isset($ids[$e]) ? $ids[$e] : (string) Str::uuid();
        unset($ids[$e]);

        return $exceptionId;
    }

    private static function resolveQueryMessage(QueryException $e): string
    {
        return match ($e->errorInfo[1] ?? null) {
            1062    => Lang::get('core-foundation::http.duplicate-entry'),
            1451    => Lang::get('core-foundation::http.foreign-key-violation'),
            default => Lang::get('core-foundation::http.database-error'),
        };
    }
}
