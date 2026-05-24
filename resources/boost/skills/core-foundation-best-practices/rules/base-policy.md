# BasePolicy Rules

## Deny by Default — Override Only What You Grant

All abilities return `Response::deny()` by default. Not overriding an ability = access denied. This is intentional — an omission is a security gate, not an open gate.

```php
use CoreFoundation\Policies\BasePolicy;
use Illuminate\Auth\Access\Response;

class OrderPolicy extends BasePolicy
{
    public function viewAny(mixed $user): Response|bool
    {
        return $user->hasPermission('orders.list');
    }

    public function view(mixed $user, mixed $model): Response|bool
    {
        return $user->id === $model->user_id
            ? Response::allow()
            : Response::deny('You do not own this order.');
    }

    public function create(mixed $user): Response|bool
    {
        return $user->hasPermission('orders.create');
    }

    // update(), delete(), restore(), forceDelete() not overridden = denied for everyone
}
```

## Available Abilities

| Ability | Signature | Default |
|---|---|---|
| `viewAny` | `($user)` | `Response::deny()` |
| `create` | `($user)` | `Response::deny()` |
| `view` | `($user, $model)` | `Response::deny()` |
| `update` | `($user, $model)` | `Response::deny()` |
| `delete` | `($user, $model)` | `Response::deny()` |
| `restore` | `($user, $model)` | `Response::deny()` |
| `forceDelete` | `($user, $model)` | `Response::deny()` |

Only override `restore` and `forceDelete` if the model uses `SoftDeletes`.

## Registration — Always in ServiceProvider `boot()`

```php
public function boot(): void
{
    parent::boot();
    Gate::policy(Order::class, OrderPolicy::class);
}
```

## Using in Controllers

```php
// Model-free ability:
$this->authorize('viewAny', Order::class);
$this->authorize('create', Order::class);

// Model-bound ability:
$this->authorize('view', $order);
$this->authorize('update', $order);
$this->authorize('delete', $order);

// Whole resource controller (registers all 5 standard abilities):
$this->authorizeResource(Order::class, 'order');
```

`AuthorizationException` is converted to a 403 JSON response by the ExceptionRenderer automatically — no try/catch needed.

## `Response::allow()` vs `true`

Both allow access. Prefer `Response::allow()` when you want to attach a message. Prefer `Response::deny('message')` over `false` so the message reaches the client.

```php
return Response::allow();             // allow with no message
return true;                          // equivalent

return Response::deny('Reason here'); // deny with message in response
return false;                         // deny with no message
```

## IDE Type Safety

Declare `@extends` PHPDoc generics on your concrete policy for IDE type inference:

```php
/**
 * @extends BasePolicy<User, Order>
 */
class OrderPolicy extends BasePolicy
{
    public function view(mixed $user, mixed $model): Response|bool
    {
        /** @var User $user */
        /** @var Order $model */
        return $user->id === $model->user_id;
    }
}
```

## Authorization Belongs in Policies, Not Services

Never call `Gate::allows()` or `$this->authorize()` inside a service method. Authorization is a controller/middleware concern.

Incorrect:
```php
class OrderService extends BaseService
{
    public function delete(int $id): void
    {
        Gate::authorize('delete', Order::find($id)); // wrong layer
        ...
    }
}
```

Correct (in controller):
```php
public function destroy(Order $order): JsonResponse
{
    $this->authorize('delete', $order); // correct layer
    ...
}
```
