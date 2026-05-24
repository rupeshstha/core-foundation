# BaseDataObject Rules

## Namespace — Always `CoreFoundation\Manipulators`

The correct namespace is `CoreFoundation\Manipulators\BaseDataObject`, not `CoreFoundation\DataObjects`.

Incorrect:
```php
use CoreFoundation\DataObjects\BaseDataObject;
```

Correct:
```php
use CoreFoundation\Manipulators\BaseDataObject;
```

## Role — Output Side Only

`BaseDataObject` carries service output. `BaseRequest` carries inbound request data. Never use `BaseDataObject` as an input carrier.

```
BaseRequest    → validates inbound data (input side)
BaseDataObject → carries service output to controller (output side)
```

## Pattern A — Generic (Quick, No Subclass)

Use for one-off or simple results where the shape doesn't need to be shared:

```php
// In a service method:
return BaseDataObject::fromArray($order->toArray());

// From an Arrayable (Eloquent model, Collection, etc.):
return BaseDataObject::fromArrayable($order);
```

## Pattern B — Typed Readonly DTO (Recommended for Stable Shapes)

Use for all shared, stable response shapes. Provides type safety, IDE autocompletion, and API doc generation.

```php
use CoreFoundation\Manipulators\BaseDataObject;
use CoreFoundation\DataObjects\Attributes\ApiResponse;
use CoreFoundation\DataObjects\Attributes\Property;

#[ApiResponse(description: 'The placed order.', status: 201, tags: ['Orders'])]
final class PlaceOrderData extends BaseDataObject
{
    public function __construct(
        #[Property(type: 'integer', description: 'Order ID.', example: 42)]
        public readonly int $orderId,

        #[Property(type: 'string', description: 'Order status.', example: 'pending')]
        public readonly string $status,

        #[Property(type: 'number', description: 'Order total.', example: 99.99)]
        public readonly float $total,
    ) {
        parent::__construct([       // required — populates the Fluent bag
            'order_id' => $orderId,
            'status'   => $status,
            'total'    => $total,
        ]);
    }

    public static function fromArray(array $data): static
    {
        return new static(
            orderId: (int)    data_get($data, 'id'),
            status:  (string) data_get($data, 'status'),
            total:   (float)  data_get($data, 'total'),
        );
    }
}
```

## Always Call `parent::__construct()` in Typed DTOs

Forgetting `parent::__construct([...])` means the Fluent bag is empty — `get()`, `toArray()`, `only()` etc. won't work correctly.

Incorrect:
```php
public function __construct(
    public readonly int $orderId,
    public readonly string $status,
) {
    // parent::__construct() missing — Fluent bag is empty
}
```

Correct:
```php
public function __construct(
    public readonly int $orderId,
    public readonly string $status,
) {
    parent::__construct(['order_id' => $orderId, 'status' => $status]);
}
```

## `#[ApiResponse]` and `#[Property]` Attributes

Add to all stable, shared DTOs for future API doc generation:

- `#[ApiResponse]` on the class — HTTP status, description, tags
- `#[Property]` on each `readonly` property — type, description, example, required, enum, format

## Fluent API (Inherited — No Re-implementation Needed)

| Category | Methods |
|---|---|
| Read | `get()`, `integer()`, `float()`, `boolean()`, `string()`, `collect()` |
| Check | `has()`, `filled()`, `missing()`, `isEmpty()`, `when()`, `unless()` |
| Write | `set()`, `fill()` |
| Output | `toArray()`, `toJson()`, `all()`, `only()`, `except()` |
