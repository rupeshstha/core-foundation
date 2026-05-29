<?php

namespace CoreFoundation\Attributes;

use Attribute;

/**
 * #[ApiRequest]
 *
 * Class-level attribute for BaseRequest subclasses.
 * Describes the request as an API operation — consumed by future doc generation.
 *
 * Applied to the class. Describe individual body fields via schema() on BaseRequest.
 *
 * USAGE:
 *
 *   #[ApiRequest(
 *       description: 'Place a new order for the authenticated user.',
 *       tags:        ['Orders'],
 *       deprecated:  false,
 *   )]
 *   class UpsertOrderRequest extends BaseRequest
 *   {
 *       protected function baseRules(): array { ... }
 *
 *       public function schema(): array
 *       {
 *           return [
 *               BodyParam::make('product')
 *                   ->type('string')
 *                   ->description('The product SKU to order.')
 *                   ->example('SKU-001')
 *                   ->required(),
 *
 *               BodyParam::make('quantity')
 *                   ->type('integer')
 *                   ->description('Number of units.')
 *                   ->example(2)
 *                   ->required(),
 *
 *               BodyParam::make('currency')
 *                   ->type('string')
 *                   ->description('ISO 4217 currency code.')
 *                   ->example('USD')
 *                   ->optional()
 *                   ->enum(['USD', 'EUR', 'GBP']),
 *           ];
 *       }
 *   }
 *
 * This attribute does nothing at runtime. It is metadata only —
 * readable via ReflectionClass for tooling, doc generation, or OpenAPI export.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ApiRequest
{
    public function __construct(
        /** Human-readable description of what this request/operation does. */
        public readonly string $description = '',

        /** Tags grouping this operation in the API docs (e.g. ['Orders', 'Billing']). */
        public readonly array $tags = [],

        /** Mark the operation as deprecated in generated docs. */
        public readonly bool $deprecated = false,
    ) {}
}
