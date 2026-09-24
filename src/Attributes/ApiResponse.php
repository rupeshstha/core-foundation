<?php

namespace CoreFoundation\Attributes;

use Attribute;

/**
 * #[ApiResponse]
 *
 * Class-level attribute for BaseDataObject subclasses.
 * Describes the DTO as an API response shape — consumed by future doc generation.
 *
 * Applied to the class, not properties. Describe individual fields with #[Property].
 *
 * USAGE:
 *
 *   #[ApiResponse(
 *       description: 'The placed order with full details.',
 *       status:      201,
 *       tags:        ['Orders'],
 *   )]
 *   final class PlaceOrderData extends BaseDataObject
 *   {
 *       #[Property(type: 'integer', description: 'The order ID.', example: 42)]
 *       public readonly int $orderId;
 *   }
 *
 * @deprecated Use dedoc/scramble automatic inference instead.
 *   Scramble infers response schemas from JsonResource::toArray() via AST analysis — no annotation needed.
 *   This attribute will be removed in a future release.
 */
#[Attribute(Attribute::TARGET_CLASS)]
final class ApiResponse
{
    public function __construct(
        /** Human-readable description of what this response represents. */
        public readonly string $description = '',

        /** The HTTP status code this response corresponds to. */
        public readonly int $status = 200,

        /** Tags grouping this response in the API docs (e.g. ['Orders', 'Billing']). */
        public readonly array $tags = [],
    ) {}
}
