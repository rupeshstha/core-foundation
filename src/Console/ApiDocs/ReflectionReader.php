<?php

namespace CoreFoundation\Console\ApiDocs;

use Throwable;
use ReflectionMethod;
use ReflectionNamedType;
use CoreFoundation\Attributes\ApiRequest;
use CoreFoundation\Attributes\ApiResponse;
use CoreFoundation\Http\Requests\BaseRequest;
use CoreFoundation\Manipulators\BaseDataObject;

/**
 * ReflectionReader
 *
 * @deprecated Part of the GenerateApiDocs pipeline which is deprecated.
 *   Use dedoc/scramble instead — it extracts metadata via AST analysis, not runtime reflection.
 *   This class will be removed in a future release.
 */
class ReflectionReader
{
    /**
     * Read all doc-relevant metadata from a scanned route.
     * Returns null when the controller method cannot be reflected (e.g. missing class).
     */
    public function read(ScannedRoute $route): ?ReadResult
    {
        try {
            $reflection = new ReflectionMethod(
                $route->controller(),
                $route->controllerMethod(),
            );
        } catch (Throwable) {
            // Controller class or method does not exist — skip silently
            return null;
        }

        $requestClass = $this->findRequestClass($reflection);
        $responseClass = $this->findResponseClass($reflection);

        return new ReadResult(
            route: $route,
            requestClass: $requestClass,
            responseClass: $responseClass,
            requestMeta: $requestClass ? $this->readRequestMeta($requestClass) : null,
            responseMeta: $responseClass ? $this->readResponseMeta($responseClass) : null,
            bodyParams: $requestClass ? $this->readBodyParams($requestClass) : [],
            properties: $responseClass ? $this->readProperties($responseClass) : [],
        );
    }

    /**
     * Find the BaseRequest subclass from the controller method's parameters.
     * Looks for a parameter whose type hint extends BaseRequest.
     *
     * @return class-string<BaseRequest>|null
     */
    private function findRequestClass(ReflectionMethod $method): ?string
    {
        foreach ($method->getParameters() as $parameter) {
            $type = $parameter->getType();

            if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
                continue;
            }

            $class = $type->getName();

            if (
                class_exists($class) &&
                is_subclass_of($class, BaseRequest::class)
            ) {
                return $class;
            }
        }

        return null;
    }

    /**
     * Find the BaseDataObject subclass from the controller method's return type.
     *
     * @return class-string<BaseDataObject>|null
     */
    private function findResponseClass(ReflectionMethod $method): ?string
    {
        $returnType = $method->getReturnType();

        if (! $returnType instanceof ReflectionNamedType) {
            return null;
        }

        $class = $returnType->getName();

        // Controllers often return JsonResponse — look one level deeper
        // by checking if the method docblock hints at a DTO.
        // For now: only match direct BaseDataObject subclass return types.
        if (
            class_exists($class) &&
            is_subclass_of($class, BaseDataObject::class)
        ) {
            return $class;
        }

        return null;
    }

    private function readRequestMeta(string $class): ?ApiRequest
    {
        return $class::getRequestMeta();
    }

    private function readResponseMeta(string $class): ?ApiResponse
    {
        return $class::getResponseMeta();
    }

    private function readBodyParams(string $class): array
    {
        // schema() is an instance method — resolve via container so
        // any constructor dependencies are injected correctly
        try {
            return app($class)->schema();
        } catch (Throwable) {
            return [];
        }
    }

    private function readProperties(string $class): array
    {
        return $class::getPropertyMeta();
    }
}
