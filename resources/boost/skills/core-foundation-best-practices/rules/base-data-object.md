# BaseDataObject (DTO) Best Practices

## Correct Namespace

The class lives in `CoreFoundation\Manipulators`, not `CoreFoundation\DataObjects`.

Incorrect:
```php
use CoreFoundation\DataObjects\BaseDataObject;
```

Correct:
```php
use CoreFoundation\Manipulators\BaseDataObject;
```

## Pattern A — One-Off Results

Use `fromArray()` directly when the shape is only needed in one place:

```php
public function create(array $data): BaseDataObject
{
    $order = Order::create($data);
    return BaseDataObject::fromArray($order->toArray());
}
```

## Pattern B — Stable Shared Shapes

Use typed `readonly` properties when the DTO is shared across services, resources, or events:

```php
final readonly class OrderData extends BaseDataObject
{
    public function __construct(
        #[Property(description: 'Order ID')]
        public int $id,

        #[Property(description: 'Current status')]
        public string $status,
    ) {
        parent::__construct(['id' => $id, 'status' => $status]);
    }

    public static function fromArray(array $data): static
    {
        return new static(id: $data['id'], status: $data['status']);
    }
}
```

## DTOs Are the Output Side — `BaseRequest` Is the Input Side

Never use a DTO for request validation. Never use a `BaseRequest` as a return type.

Incorrect:
```php
public function create(OrderData $dto): OrderData
{
    // if $dto came from a form submission, validation was skipped
}
```

Correct:
```php
// Input validation: BaseRequest
// Business logic: service receives $request->validated() or a typed DTO from the controller
// Output: BaseDataObject subclass returned by the service
```
