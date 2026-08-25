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

`handleException()` is Layer 3 — the last-resort safety net. Every call to a service or repository needs it. Catch `Throwable`, not `Exception` — this layer's entire job is to guarantee the response envelope even for PHP `Error`s (`TypeError`, `ArgumentCountError`, etc.), not only `Exception` subclasses. Narrowing it to `Exception` lets Errors escape uncaught and produce a raw, non-enveloped page instead of the standard JSON error shape.

Name the caught variable for what it is — never a one-letter name like `$e`. `$exception` is the default; when a catch block is narrowed to a specific type, name it after that type (`$queryException`, `$validationException`).

Incorrect (no safety net, and a one-letter variable):
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
    } catch (Throwable $exception) {
        return $this->handleException($exception);
    }

    return $this->createdResponse(
        message: $this->lang('create-success'),
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
} catch (OrderNotFoundException $orderNotFoundException) {
    return response()->json(['message' => $orderNotFoundException->getMessage()], 404);
} catch (Throwable $exception) {
    return $this->handleException($exception);
}
```

Correct:
```php
try {
    $order = $this->service->find($id);
} catch (Throwable $exception) {
    return $this->handleException($exception);  // OrderNotFoundException renders itself automatically
}
```

## Controllers Never Query — Not Even "Just This Once"

A controller talks to a service, which talks to a repository. There is no third path. If a controller ever needs data shaped differently than what an existing service method returns, add a method to the service (which calls a repository method), not a query inline in the controller action.

If an ad hoc query genuinely cannot be avoided in the short term, it must still go through the repository's cache layer — never a bare `Model::query()` call sitting in controller code with no invalidation story:

Incorrect:
```php
public function summary(): JsonResponse
{
    $total = Order::where('status', 'completed')->sum('total');  // uncached, uncoupled from repository invalidation

    return $this->successResponse(payload: ['total' => $total]);
}
```

Correct — add a cached method to the repository (see `rules/base-repository.md`) and call it through the service:
```php
// OrderRepository
public function completedTotal(): int
{
    return $this->cache->remember(/* ... */); // or a method already in cachedMethods()
}

// Controller
public function summary(): JsonResponse
{
    try {
        $total = $this->service->completedTotal();
    } catch (Throwable $exception) {
        return $this->handleException($exception);
    }

    return $this->successResponse(payload: ['total' => $total]);
}
```

## Every User-Facing Message Uses `$this->lang()` — Never a Hardcoded String

`$this->lang('key')` resolves to `trans('{prefix}.key')`. Prefix defaults to the controller name minus `Controller` in kebab-case (`OrderController` → `order`). `php artisan core:make {Name}` generates `lang/en/{name}.php` automatically alongside the controller — add new keys there, never inline the English string in the controller.

Incorrect:
```php
return $this->createdResponse(message: 'Order created successfully.', payload: new OrderResource($result));
```

Correct:
```php
// resources/lang/en/order.php (or lang/en/order.php in the app)
return ['create-success' => 'Order created successfully.', 'delete-success' => 'Order deleted successfully.'];

// In controller:
return $this->createdResponse(message: $this->lang('create-success'), payload: new OrderResource($result));
```

For anything outside a controller (a job, a domain exception, a static class), use `Lang::get()` instead — see `CoreFoundation\Support\Lang`. Never call Laravel's `trans()`/`__()` directly and never concatenate a message from parts; every message is a single translation key.

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
        } catch (Throwable $exception) {
            return $this->handleException($exception);
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
        } catch (Throwable $exception) {
            return $this->handleException($exception);
        }

        return $this->noContentResponse();
    }
}
```
