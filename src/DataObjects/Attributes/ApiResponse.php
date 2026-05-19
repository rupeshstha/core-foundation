<?php

namespace CoreFoundation\DataObjects\Attributes;

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
 * This attribute does nothing at runtime. It is metadata only —
 * readable via ReflectionClass for tooling, doc generation, or OpenAPI export.
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
