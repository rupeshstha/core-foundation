<?php

namespace CoreFoundation\Manipulators;

use ReflectionClass;
use ReflectionProperty;
use Illuminate\Support\Fluent;
use CoreFoundation\Attributes\Property;
use CoreFoundation\Attributes\ApiResponse;
use Illuminate\Contracts\Support\Arrayable;

/**
 * BaseDataObject
 *
 * Base class for all response DTOs in the application.
 * Extends Laravel's Fluent — every Fluent method is available for free.
 *
 * Carry metadata for future API doc generation using PHP Attributes:
 *   #[ApiResponse] on the class   — describes the response as a whole
 *   #[Property]    on properties  — describes each field
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ ROLE IN THE ARCHITECTURE                                                    │
 * │                                                                             │
 * │  BaseRequest    ──► validates input  ──► FormRequest (Laravel)              │
 * │  BaseDataObject ──► carries output  ──► service → controller → response    │
 * │                                                                             │
 * │ BaseDataObject is the RESPONSE side only. It is what a service returns     │
 * │ and what the controller serialises into a JSON response. It is NOT used     │
 * │ to carry inbound request data — that is BaseRequest's job.                 │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ TWO USAGE PATTERNS                                                          │
 * │                                                                             │
 * │ PATTERN A — Generic (quick, no subclass)                                    │
 * │ Use when the response shape is simple or one-off.                           │
 * │                                                                             │
 * │   return BaseDataObject::make($order->toArray());                           │
 * │                                                                             │
 * │ PATTERN B — Typed readonly DTO (recommended)                                │
 * │ Use for all stable, shared response shapes. Gives type safety, IDE          │
 * │ autocompletion, self-documenting code, and future API doc generation.       │
 * │                                                                             │
 * │   #[ApiResponse(description: 'The placed order.', status: 201, tags: ['Orders'])]
 * │   final class PlaceOrderData extends BaseDataObject                         │
 * │   {                                                                         │
 * │       public function __construct(                                          │
 * │           #[Property(type: 'integer', description: 'Order ID.', example: 42)]
 * │           public readonly int $orderId,                                     │
 * │                                                                             │
 * │           #[Property(type: 'number', description: 'Order total.', example: 99.99)]
 * │           public readonly float $total,                                     │
 * │                                                                             │
 * │           #[Property(type: 'string', description: 'Currency code.', example: 'USD')]
 * │           public readonly string $currency = 'USD',                         │
 * │       ) {                                                                   │
 * │           parent::__construct([                                             │
 * │               'order_id' => $orderId,                                       │
 * │               'total'    => $total,                                         │
 * │               'currency' => $currency,                                      │
 * │           ]);                                                               │
 * │       }                                                                     │
 * │                                                                             │
 * │       public static function fromArray(array $data): static                 │
 * │       {                                                                     │
 * │           return new static(                                                │
 * │               orderId:  (int)   data_get($data, 'id'),                      │
 * │               total:    (float) data_get($data, 'amount'),                  │
 * │               currency: (string) data_get($data, 'currency', 'USD'),        │
 * │           );                                                                │
 * │       }                                                                     │
 * │                                                                             │
 * │       // Controls exact JSON serialisation — property names ≠ JSON keys     │
 * │       public function toArray(): array                                      │
 * │       {                                                                     │
 * │           return [                                                          │
 * │               'order_id' => $this->orderId,                                 │
 * │               'total'    => $this->total,                                   │
 * │               'currency' => $this->currency,                                │
 * │           ];                                                                │
 * │       }                                                                     │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ FULL FLUENT API (inherited — no reimplementation)                           │
 * │                                                                             │
 * │ Read:    get, value, integer, float, boolean, string, date, enum, collect   │
 * │ Check:   has, filled, missing, isEmpty, isNotEmpty, when, unless, whenHas   │
 * │ Write:   set, fill, __call (dynamic setter)                                 │
 * │ Output:  toArray, toJson, toPrettyJson, all, only, except, collect          │
 * │ Extra:   ArrayAccess, IteratorAggregate, Macroable, scope                   │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
class BaseDataObject extends Fluent
{
    /**
     * Build from a plain array — primary path for generic (Pattern A) use
     * and the override target for typed (Pattern B) DTOs.
     *
     * Typed subclasses SHOULD override this to map array keys to typed
     * constructor arguments with explicit casting:
     *
     *   public static function fromArray(array $data): static
     *   {
     *       return new static(
     *           orderId:  (int)   data_get($data, 'id'),
     *           total:    (float) data_get($data, 'amount'),
     *       );
     *   }
     */
    public static function fromArray(array $data): static
    {
        return new static($data);
    }

    /**
     * Build from any Arrayable — Eloquent model, Collection, another DTO.
     *
     *   PlaceOrderData::fromArrayable($order);
     */
    public static function fromArrayable(Arrayable $source): static
    {
        return static::fromArray($source->toArray());
    }

    /**
     * Read the #[ApiResponse] attribute from this class, if present.
     *
     * Returns null when the class has no #[ApiResponse] attribute — always
     * check for null before reading. The attribute is optional so plain
     * BaseDataObject usage works without requiring it.
     *
     * Future doc generation calls this to read class-level metadata:
     *
     *   $meta = PlaceOrderData::getResponseMeta();
     *   $meta->status;      // 201
     *   $meta->description; // 'The placed order.'
     *   $meta->tags;        // ['Orders']
     */
    public static function getResponseMeta(): ?ApiResponse
    {
        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(ApiResponse::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    /**
     * Read all #[Property] attributes from this class's properties.
     *
     * Returns a keyed array: [ propertyName => Property attribute instance ]
     *
     * Future doc generation calls this to read field-level metadata:
     *
     *   $props = PlaceOrderData::getPropertyMeta();
     *   foreach ($props as $name => $meta) {
     *       $meta->type;        // 'integer'
     *       $meta->description; // 'The order ID.'
     *       $meta->example;     // 42
     *       $meta->required;    // true
     *   }
     */
    public static function getPropertyMeta(): array
    {
        $reflection = new ReflectionClass(static::class);
        $result = [];

        foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
            $attributes = $property->getAttributes(Property::class);

            if (! empty($attributes)) {
                $result[$property->getName()] = $attributes[0]->newInstance();
            }
        }

        return $result;
    }

    /**
     * Export the full schema of this DTO as an OpenAPI-compatible array.
     *
     * Combines class-level (#[ApiResponse]) and property-level (#[Property])
     * metadata into one structure. Future doc generation calls this directly.
     *
     *   PlaceOrderData::toOpenApiSchema();
     *   // [
     *   //     'description' => 'The placed order.',
     *   //     'status'      => 201,
     *   //     'tags'        => ['Orders'],
     *   //     'properties'  => [
     *   //         'orderId' => ['type' => 'integer', 'description' => '...', ...],
     *   //     ],
     *   //     'required'    => ['orderId', 'total'],
     *   // ]
     */
    public static function toOpenApiSchema(): array
    {
        $meta = static::getResponseMeta();
        $properties = static::getPropertyMeta();

        $schema = [
            'description' => $meta?->description ?? '',
            'status' => $meta?->status ?? 200,
            'tags' => $meta?->tags ?? [],
            'properties' => [],
            'required' => [],
        ];

        foreach ($properties as $name => $property) {
            $field = ['type' => $property->type];

            if ($property->description) {
                $field['description'] = $property->description;
            }
            if ($property->example !== null) {
                $field['example'] = $property->example;
            }
            if ($property->format) {
                $field['format'] = $property->format;
            }
            if ($property->enum) {
                $field['enum'] = $property->enum;
            }
            if ($property->items) {
                $field['items'] = ['type' => $property->items];
            }

            $schema['properties'][$name] = $field;

            if ($property->required) {
                $schema['required'][] = $name;
            }
        }

        return $schema;
    }
}
