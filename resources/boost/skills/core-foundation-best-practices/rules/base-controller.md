# BaseController Best Practices

## Use Envelope Helpers — Never `response()->json()`

`response()->json()` bypasses the envelope and breaks agent/client parsing.

Incorrect:
```php
return response()->json(['data' => $order], 200);
```

Correct:
```php
return $this->successResponse(payload: new OrderResource($order));    // 200
return $this->createdResponse(payload: new OrderResource($order));    // 201
return $this->noContentResponse();                                    // 204
return $this->paginatedResponse($paginator);                          // 200 + pagination meta
```

## Wrap Every Service Call with `handleException()`

`handleException()` is Layer 3 — the last-resort safety net. Every call to a service or repository needs it.

Incorrect (no safety net):
```php
public function store(StoreOrderRequest $request): JsonResponse
{
    $result = $this->service->create($request->validated());
    return $this->createdResponse(payload: new OrderResource($result));
}
```

Correct:
```php
public function store(StoreOrderRequest $request): JsonResponse
{
    try {
        $result = $this->service->create($request->validated());
    } catch (Throwable $e) {
        return $this->handleException($e);
    }

    return $this->createdResponse(
        message: $this->lang('created'),
        payload: new OrderResource($result),
    );
}
```

## Never Catch Specific Exceptions in a Controller

Domain exceptions implement `render()` — Laravel calls it automatically. A controller catch-block is redundant and duplicates the rendering logic.

Incorrect:
```php
try {
    $order = $this->service->find($id);
} catch (OrderNotFoundException $e) {
    return response()->json(['message' => $e->getMessage()], 404);
} catch (Throwable $e) {
    return $this->handleException($e);
}
```

Correct:
```php
try {
    $order = $this->service->find($id);
} catch (Throwable $e) {
    return $this->handleException($e);  // OrderNotFoundException renders itself automatically
}
```

## Translation via `$this->lang()`

`$this->lang('key')` resolves to `trans('{prefix}.key')`. Prefix defaults to the controller name minus `Controller` in kebab-case (`OrderController` → `order`).

```php
// resources/lang/en/order.php
return ['created' => 'Order created.', 'deleted' => 'Order deleted.'];

// In controller:
return $this->createdResponse(message: $this->lang('created'), payload: new OrderResource($result));
```

Override `langPrefix()` if the default doesn't fit.

## Standard Controller Structure

```php
final class OrderController extends BaseController
{
    public function __construct(
        private readonly OrderService $service,
    ) {}

    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $result = $this->service->create($request->validated());
        } catch (Throwable $e) {
            return $this->handleException($e);
        }

        return $this->createdResponse(
            message: $this->lang('created'),
            payload: new OrderResource($result),
        );
    }

    public function destroy(Order $order): JsonResponse
    {
        $this->authorize('delete', $order);

        try {
            $this->service->delete($order->id);
        } catch (Throwable $e) {
            return $this->handleException($e);
        }

        return $this->noContentResponse();
    }
}
```
