# BasePolicy Best Practices

## All Abilities Deny by Default — Omission Is a Security Gate

Every ability returns `Response::deny()` until overridden. An ability you don't override is an ability that is always denied. This is intentional — misconfiguration defaults to secure.

```php
class OrderPolicy extends BasePolicy
{
    // view(), create(), update(), delete() all deny by default
    // Override only the ones you want to allow:

    public function view(mixed $user, Model $model): Response
    {
        /** @var Order $model */
        return $user->id === $model->user_id
            ? Response::allow()
            : Response::deny('This order belongs to another user.');
    }
}
```

## Parameter Types Must Stay as `Model` — Not a Concrete Class

PHP parameter types are contravariant. Narrowing `Model $model` to `Order $model` is a fatal PHP error.

Incorrect (fatal at class load time):
```php
public function view(mixed $user, Order $model): Response  // PHP fatal
```

Correct:
```php
public function view(mixed $user, Model $model): Response
{
    /** @var Order $model */  // @var for IDE narrowing — not a type hint
    return $user->id === $model->user_id ? Response::allow() : Response::deny();
}
```

## Register via `Gate::policy()` in a ServiceProvider

```php
// In OrderServiceProvider::boot():
Gate::policy(Order::class, OrderPolicy::class);
```

## Use `$this->authorize()` in Controllers — Never Manual Checks

`$this->authorize()` calls the policy and the `ExceptionRenderer` converts `AuthorizationException` to a 403 JSON response automatically.

Incorrect:
```php
if (! $request->user()->can('view', $order)) {
    return $this->errorResponse('Forbidden.', 403);
}
```

Correct:
```php
$this->authorize('view', $order);  // throws AuthorizationException on deny → 403 JSON
```
