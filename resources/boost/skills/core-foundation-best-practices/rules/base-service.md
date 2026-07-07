# BaseService Best Practices

## Always Return `BaseDataObject` — Never `JsonResponse`

Services contain business logic. They must not know about HTTP. Returning `JsonResponse` from a service couples the business layer to the transport layer.

Incorrect:
```php
public function create(array $data): JsonResponse
{
    $order = Order::create($data);
    return response()->json(['id' => $order->id]);
}
```

Correct:
```php
public function create(array $data): OrderData
{
    $order = Order::create($data);
    return OrderData::fromArray($order->toArray());
}
```

## Use `HasPipeline` for Execution Hooks — Never Events

Pipes can modify the payload before and after the core. Events cannot. Use pipes when the result must change; use events for fire-and-forget side effects.

Incorrect (using events for flow control):
```php
public function create(array $data): OrderData
{
    event(new BeforeOrderCreated($data));  // can't modify $data
    $order = Order::create($data);
    return OrderData::fromArray($order->toArray());
}
```

Correct:
```php
public function create(array $data): OrderData
{
    return $this->throughPipes('create', $data, function (array $validated): OrderData {
        $order = Order::create($validated);
        $result = OrderData::fromArray($order->toArray());

        OrderCreated::dispatch($result);  // fire-and-forget pub/sub

        return $result;
    });
}

// Register pipes from a ServiceProvider, never here:
// OrderService::addPipe('create', ValidateInventoryPipe::class);
```

## Use Class-Based Events — Never String-Keyed

String-keyed events have no type safety and cannot be discovered by static analysis.

Incorrect:
```php
Event::dispatch('order.created', $order);
```

Correct:
```php
OrderCreated::dispatch($result);
```

## `static::class` in All Registries

`self::class` breaks inheritance — subclasses of the service would share the same pipe registry as the parent.

Incorrect:
```php
protected static array $pipes = [];

public static function addPipe(string $hook, string $pipe): void
{
    static::$pipes[self::class][$hook][] = $pipe;  // wrong
}
```

Correct:
```php
static::$pipes[static::class][$hook][] = $pipe;
```
