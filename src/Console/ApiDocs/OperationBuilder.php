<?php

namespace CoreFoundation\Console\ApiDocs;

use CoreFoundation\Attributes\Property;
use CoreFoundation\Attributes\BodyParam;

/**
 * OperationBuilder
 *
 * @deprecated Part of the GenerateApiDocs pipeline which is deprecated.
 *   Use dedoc/scramble instead — it builds operations via AST-based inference.
 *   This class will be removed in a future release.
 */
class OperationBuilder
{
    public function build(ReadResult $result): array
    {
        $operation = [
            'operationId' => $result->resolveOperationId(),
            'tags' => $result->resolveTags(),
            'summary' => $result->resolveDescription(),
            'responses' => $this->buildResponses($result),
        ];

        if ($result->isDeprecated()) {
            $operation['deprecated'] = true;
        }

        // Path parameters — extracted from URI template e.g. /orders/{order}
        $pathParams = $this->buildPathParameters($result->route->uri);
        if (! empty($pathParams)) {
            $operation['parameters'] = $pathParams;
        }

        // Request body — only for mutating methods with a BaseRequest
        if ($result->hasRequest() && $this->methodHasBody($result->route->method)) {
            $operation['requestBody'] = $this->buildRequestBody($result->bodyParams);
        }

        return $operation;
    }

    /**
     * @param  BodyParam[]  $params
     */
    private function buildRequestBody(array $params): array
    {
        $properties = [];
        $required = [];

        foreach ($params as $param) {
            $properties[$param->getName()] = $this->bodyParamToSchema($param);

            if ($param->isRequired()) {
                $required[] = $param->getName();
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];

        if (! empty($required)) {
            $schema['required'] = $required;
        }

        return [
            'required' => true,
            'content' => [
                'application/json' => [
                    'schema' => $schema,
                ],
            ],
        ];
    }

    private function bodyParamToSchema(BodyParam $param): array
    {
        $schema = ['type' => $param->getType()];

        if ($param->getFormat()) {
            $schema['format'] = $param->getFormat();
        }
        if ($param->getDescription()) {
            $schema['description'] = $param->getDescription();
        }
        if ($param->getExample() !== null) {
            $schema['example'] = $param->getExample();
        }
        if ($param->getEnum()) {
            $schema['enum'] = $param->getEnum();
        }
        if ($param->getMinimum() !== null) {
            $schema['minimum'] = $param->getMinimum();
        }
        if ($param->getMaximum() !== null) {
            $schema['maximum'] = $param->getMaximum();
        }
        if ($param->getItems()) {
            $schema['items'] = ['type' => $param->getItems()];
        }

        return $schema;
    }

    private function buildResponses(ReadResult $result): array
    {
        $responses = [];

        // Success response
        $status = (string) $result->responseStatus();
        $responses[$status] = $result->hasResponse()
            ? $this->buildSuccessResponse($result)
            : ['description' => 'Success'];

        // Standard error responses always present
        $responses['401'] = ['description' => 'Unauthenticated.'];
        $responses['403'] = ['description' => 'This action is unauthorized.'];
        $responses['422'] = $this->build422Response();
        $responses['500'] = ['description' => 'Server error.'];

        return $responses;
    }

    private function buildSuccessResponse(ReadResult $result): array
    {
        $properties = [];
        $required = [];

        foreach ($result->properties as $name => $property) {
            /** @var Property $property */
            $properties[$name] = $this->propertyToSchema($property);

            if ($property->required) {
                $required[] = $name;
            }
        }

        $schema = ['type' => 'object', 'properties' => $properties];

        if (! empty($required)) {
            $schema['required'] = $required;
        }

        return [
            'description' => $result->responseMeta->description ?? 'Success.',
            'content' => [
                'application/json' => [
                    'schema' => $schema,
                ],
            ],
        ];
    }

    private function propertyToSchema(Property $property): array
    {
        $schema = ['type' => $property->type];

        if ($property->format) {
            $schema['format'] = $property->format;
        }
        if ($property->description) {
            $schema['description'] = $property->description;
        }
        if ($property->example !== null) {
            $schema['example'] = $property->example;
        }
        if ($property->enum) {
            $schema['enum'] = $property->enum;
        }
        if ($property->items) {
            $schema['items'] = ['type' => $property->items];
        }

        return $schema;
    }

    private function build422Response(): array
    {
        return [
            'description' => 'Validation error.',
            'content' => [
                'application/json' => [
                    'schema' => [
                        'type' => 'object',
                        'properties' => [
                            'message' => [
                                'type' => 'string',
                                'example' => 'The given data was invalid.',
                            ],
                            'errors' => [
                                'type' => 'object',
                                'additionalProperties' => [
                                    'type' => 'array',
                                    'items' => ['type' => 'string'],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    /**
     * Extract path parameters from a URI template.
     * /api/orders/{order}/items/{item} → [order param, item param]
     */
    private function buildPathParameters(string $uri): array
    {
        preg_match_all('/\{(\w+)\}/', $uri, $matches);

        return array_map(fn ($name) => [
            'name' => $name,
            'in' => 'path',
            'required' => true,
            'schema' => ['type' => 'integer'],
        ], $matches[1]);
    }

    private function methodHasBody(string $method): bool
    {
        return in_array(strtoupper($method), ['POST', 'PUT', 'PATCH'], true);
    }
}
