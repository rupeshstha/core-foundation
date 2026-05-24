# BaseController Rules

## Always Extend BaseController

Every API controller must extend `CoreFoundation\Http\Controllers\BaseController`. Never extend Laravel's `Controller` directly.

## Response Helpers — Never `response()->json()`

Use the envelope helpers exclusively. Never call `response()->json()` in a controller method.

Incorrect:
```php
return response()->json(['data' => $order], 200);
```

Correct:
```php
return $this->successResponse(message: 'Order retrieved.', payload: new OrderResource($order));   // 200
return $this->createdResponse(message: 'Order created.', payload: new OrderResource($order));    // 201
return $this->paginatedResponse(message: 'Orders fetched.', payload: new OrderCollection($page)); // 200
return $this->noContentResponse();                                                                // 204
```

## Exception Handling — Always Use `handleException()`

Wrap every service and repository call in `try/catch (Throwable $e)` and delegate to `handleException()`. It generates a UUID, logs with context, and returns 500.

Incorrect (no safety net):
```php
public function store(StoreOrderRequest $request): JsonResponse
{
    $result = $this->service->place($request->validated());
    return $this->createdResponse('Order created.', new OrderResource($result));
}
```

Correct:
```php
public function store(StoreOrderRequest $request): JsonResponse
{
    try {
        $result = $this->service->place($request->validated());
    } catch (Throwable $e) {
        return $this->handleException($e);
    }

    return $this->createdResponse(
        message: $this->lang('create-success'),
        payload: new OrderResource($result),
    );
}
```

## Never Catch Specific Exceptions in Controllers

Domain exceptions implement `render()` — Laravel calls it automatically. There is no reason to catch them in a controller.

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
    return $this->handleException($e); // only for unexpected exceptions
}
```

`OrderNotFoundException` (a `BaseApiException` subclass) is rendered by Layer 1/2 automatically.

## Standard Controller Structure

```php
use CoreFoundation\Http\Controllers\BaseController;

final class OrderController extends BaseController
{
    public function __construct(
        private readonly OrderService    $service,
        private readonly OrderRepository $repository,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            $orders = $this->repository->fetchAll($request->query());
        } catch (Throwable $e) {
            return $this->handleException($e);
        }

        return $this->paginatedResponse(
            message: $this->lang('fetch-success'),
            payload: new OrderCollection($orders),
        );
    }

    public function store(StoreOrderRequest $request): JsonResponse
    {
        try {
            $result = $this->service->place($request->validated());
        } catch (Throwable $e) {
            return $this->handleException($e);
        }

        return $this->createdResponse(
            message: $this->lang('create-success'),
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

## Translation via `HasLang`

`$this->lang('key')` resolves to `trans('{prefix}.key')`. The prefix defaults to the controller's snake-case name minus `Controller` (e.g., `OrderController` → `order`).

```php
// lang/en/order.php
return [
    'fetch-success'  => 'Orders fetched successfully.',
    'create-success' => 'Order created successfully.',
    'update-success' => 'Order updated successfully.',
    'delete-success' => 'Order deleted successfully.',
];
```

Override `langPrefix()` if the default doesn't fit.

## Extending the Exception Map

Override `knownExceptions()` only when `handleException()` needs to map a specific exception to a non-500 status in this controller:

```php
protected function knownExceptions(): array
{
    return array_merge(parent::knownExceptions(), [
        InsufficientInventoryException::class => [
            'status'  => 422,
            'message' => 'Insufficient inventory for this order.',
            'errors'  => [],
        ],
    ]);
}
```
