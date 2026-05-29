<?php

namespace CoreFoundation\Attributes;

use Attribute;

/**
 * #[Property]
 *
 * Property-level attribute for BaseDataObject subclasses.
 * Describes a single field on a response DTO — consumed by future doc generation.
 *
 * Applied to each readonly property on a typed DTO. Works alongside
 * the class-level #[ApiResponse] attribute to fully describe the response shape.
 *
 * USAGE:
 *
 *   final class PlaceOrderData extends BaseDataObject
 *   {
 *       #[Property(
 *           type:        'integer',
 *           description: 'The unique order identifier.',
 *           example:     42,
 *           required:    true,
 *       )]
 *       public readonly int $orderId,
 *
 *       #[Property(
 *           type:        'string',
 *           description: 'ISO 4217 currency code.',
 *           example:     'USD',
 *           required:    false,
 *           enum:        ['USD', 'EUR', 'GBP'],
 *       )]
 *       public readonly string $currency,
 *
 *       #[Property(
 *           type:        'string',
 *           description: 'ISO 8601 timestamp when the order was placed.',
 *           example:     '2024-01-15T09:30:00Z',
 *           format:      'date-time',
 *       )]
 *       public readonly string $placedAt,
 *   }
 *
 * TYPE VALUES — use OpenAPI-compatible type strings so future doc export
 * maps directly to an OpenAPI schema with no transformation:
 *   'string' | 'integer' | 'number' | 'boolean' | 'array' | 'object'
 *
 * FORMAT VALUES (optional, OpenAPI format hints):
 *   'date-time' | 'date' | 'email' | 'uuid' | 'uri' | 'password' | 'binary'
 *
 * This attribute does nothing at runtime. It is metadata only —
 * readable via ReflectionProperty for tooling, doc generation, or OpenAPI export.
 */
#[Attribute(Attribute::TARGET_PROPERTY)]
final class Property
{
    public function __construct(
        /** OpenAPI-compatible type: 'string' | 'integer' | 'number' | 'boolean' | 'array' | 'object' */
        public readonly string $type = 'string',

        /** Human-readable description of what this field represents. */
        public readonly string $description = '',

        /** A concrete example value for docs and testing. */
        public readonly mixed $example = null,

        /** Whether this field is always present in the response. */
        public readonly bool $required = true,

        /** Allowed values — maps to OpenAPI 'enum'. */
        public readonly array $enum = [],

        /** OpenAPI format hint e.g. 'date-time', 'uuid', 'email'. */
        public readonly string $format = '',

        /** For type='array', the type of each item e.g. 'string', 'integer'. */
        public readonly string $items = '',
    ) {}
}
