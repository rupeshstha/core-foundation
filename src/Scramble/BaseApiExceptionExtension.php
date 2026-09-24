<?php

namespace CoreFoundation\Scramble;

use ReflectionClass;
use ReflectionProperty;
use Dedoc\Scramble\Support\Type\Type;
use Dedoc\Scramble\Support\Type\ObjectType;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Response;
use CoreFoundation\Exceptions\BaseApiException;
use Dedoc\Scramble\Extensions\ExceptionToResponseExtension;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;

/**
 * Maps any class extending CoreFoundation\Exceptions\BaseApiException to the
 * CoreFoundation error envelope:
 *
 *   { "message": "string", "errors": { "field": ["string"] }, "exception_id": "string|null" }
 *
 * The HTTP status code is read from the exception's $status property (default 500).
 */
class BaseApiExceptionExtension extends ExceptionToResponseExtension
{
    public function shouldHandle(Type $type): bool
    {
        return $type instanceof ObjectType
            && $type->isInstanceOf(BaseApiException::class);
    }

    public function toResponse(Type $type): ?Response
    {
        $status = 500;

        if ($type instanceof ObjectType) {
            $className = ltrim($type->name, '\\');
            if (class_exists($className) && is_a($className, BaseApiException::class, true)) {
                $reflection = new ReflectionProperty($className, 'status');
                $reflection->setAccessible(true);
                $instance = (new ReflectionClass($className))->newInstanceWithoutConstructor();
                $status = (int) $reflection->getValue($instance);
            }
        }

        $errorBodyType = (new OpenApiTypes\ObjectType)
            ->addProperty(
                'message',
                (new OpenApiTypes\StringType)->setDescription('Human-readable error message.')
            )
            ->addProperty(
                'errors',
                (new OpenApiTypes\ObjectType)
                    ->setDescription('Field-level validation errors, if any.')
                    ->additionalProperties(
                        (new OpenApiTypes\ArrayType)->setItems(new OpenApiTypes\StringType)
                    )
            )
            ->addProperty(
                'exception_id',
                (new OpenApiTypes\StringType)
                    ->setDescription('UUID present only on fatal 500 errors for tracing.')
                    ->setNullable(true)
            )
            ->setRequired(['message', 'errors']);

        return Response::make($status)
            ->setDescription('Domain exception')
            ->setContent('application/json', Schema::fromType($errorBodyType));
    }
}
