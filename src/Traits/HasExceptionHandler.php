<?php

namespace CoreFoundation\Traits;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

trait HasExceptionHandler
{
    protected array $exceptionMessages = [];
    protected array $exceptionStatusCodes = [];

    public function getExceptionStatus(object $exception): int
    {
        $defaultResponseCode = $this->getDefaultExceptionCode($exception);
        $exceptionClass = $this->getExceptionClass($exception);

        $defaultExceptionCodes = $this->getExceptionCodes();
        $exceptionStatus = array_merge(
            $defaultExceptionCodes,
            $this->exceptionStatusCodes
        )[$exceptionClass] ?? $defaultResponseCode;

        return $exceptionStatus;
    }

    public function getExceptionMessage(object $exception): string
    {
        $exceptionClass = $this->getExceptionClass($exception);

        $exceptionMessage = array_merge([
            ValidationException::class => json_encode(
                method_exists($exception, "errors")
                    ? $exception->errors()
                    : []
            ),
            NotFoundHttpException::class => "Record not found",
        ], $this->exceptionMessages)[$exceptionClass] ?? $exception->getMessage();

        /**
         * TODO:: Add pdo exception errorInfo code to map pdo exception message.
         * handle query PDOException
         */
        if ($exception instanceof QueryException) {
            $exceptionMessage = match ($exception->errorInfo[1]) {
                1062 => "Duplicate Entry.",
                1451 => "Cannot delete or update a parent row.",
                default => "Something went wrong. Please try again later",
            };
        }

        return $exceptionMessage;
    }

    private function getDefaultExceptionCode(object $exception): int
    {
        $defaultResponseCode = $exception->getCode() != 0
            ? $exception->getCode()
            : Response::HTTP_INTERNAL_SERVER_ERROR;

        return (int) $defaultResponseCode;
    }

    private function getExceptionClass(object $exception): string
    {
        $exceptionClass = get_class($exception);
        // if ($exception instanceof UnverifiedUserException) {
        //     $exceptionClass = ValidationException::class;
        // }

        return $exceptionClass;
    }

    private function getExceptionCodes(): array
    {
        return [
            // UnverifiedUserException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
            ValidationException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
            ModelNotFoundException::class => Response::HTTP_NOT_FOUND,
            QueryException::class => Response::HTTP_BAD_REQUEST,
            UnauthorizedHttpException::class => Response::HTTP_UNAUTHORIZED,
            AuthorizationException::class => Response::HTTP_UNAUTHORIZED,
            NotFoundHttpException::class => Response::HTTP_NOT_FOUND,
        ];
    }

    private function setFatalExceptionLog(object $exception): void
    {
        $exceptionStatusCode = $this->getDefaultExceptionCode($exception);
        $fatalExceptions = $this->getExceptionCodes();

        unset($fatalExceptions[QueryException::class]);

        $message = $this->getExceptionMessage($exception);
        $context = $this->getExceptionContext($exception);

        if (!in_array($exceptionStatusCode, $fatalExceptions)) {
            Log::error(
                message: $message,
                context: $context
            );
        }
    }

    /**
     * Get exception context
     *
     * @param  object $exception
     * @return array
     */
    private function getExceptionContext(object $exception): array
    {
        $context = [
            "request" => [
                "headers" => request()->header(),
                "parameters" => request()->query(),
                "request_url_path" => request()->fullUrl(),
            ],
            "trace" => $exception->getTrace(),
        ];

        // handle query exception
        if ($exception instanceof QueryException) {
            $context = array_merge($context, [
                "trace" => [
                    "sql" => $exception->getMessage(),
                    "data" => $exception->getBindings(),
                ]
            ]);
        }

        return $context;
    }
}
