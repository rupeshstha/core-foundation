<?php

namespace CoreFoundation\Scramble;

use Dedoc\Scramble\Support\RouteInfo;
use Dedoc\Scramble\Support\Generator\Schema;
use Dedoc\Scramble\Support\Generator\Response;
use Dedoc\Scramble\Support\Generator\Operation;
use Dedoc\Scramble\Extensions\OperationExtension;
use Dedoc\Scramble\Support\Generator\Types as OpenApiTypes;

/**
 * Wraps every inferred 200/201 response body in the CoreFoundation envelope:
 *
 *   { "message": "string", "payload": <original-schema>, "meta": { ... } }
 *
 * 204 responses are left as-is (no body).
 */
class CoreFoundationEnvelopeTransformer extends OperationExtension
{
    public function handle(Operation $operation, RouteInfo $routeInfo): void
    {
        foreach ($operation->responses ?? [] as $response) {
            if (! $response instanceof Response) {
                continue;
            }

            if (! in_array($response->code, [200, 201], strict: true)) {
                continue;
            }

            if (! isset($response->content['application/json'])) {
                continue;
            }

            $original = $response->content['application/json'];

            $envelopeType = (new OpenApiTypes\ObjectType)
                ->addProperty('message', (new OpenApiTypes\StringType)->setDescription('Human-readable status message.'))
                ->addProperty('payload', $original instanceof Schema ? $original->type : new OpenApiTypes\ObjectType)
                ->addProperty('meta', $this->buildMetaType())
                ->setRequired(['message', 'payload', 'meta']);

            $response->setContent('application/json', Schema::fromType($envelopeType));
        }
    }

    private function buildMetaType(): OpenApiTypes\ObjectType
    {
        $paginationType = (new OpenApiTypes\ObjectType)
            ->addProperty('total', new OpenApiTypes\IntegerType)
            ->addProperty('per_page', new OpenApiTypes\IntegerType)
            ->addProperty('current_page', new OpenApiTypes\IntegerType)
            ->addProperty('last_page', new OpenApiTypes\IntegerType)
            ->addProperty('from', new OpenApiTypes\IntegerType)
            ->addProperty('to', new OpenApiTypes\IntegerType);

        return (new OpenApiTypes\ObjectType)
            ->addProperty('pagination', $paginationType)
            ->setDescription('Pagination metadata — present on list endpoints, null otherwise.');
    }
}
