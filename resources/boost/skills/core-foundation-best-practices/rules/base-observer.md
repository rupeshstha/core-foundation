# BaseObserver Best Practices

## Override Only What You Need

All lifecycle methods default to a no-op. Methods not declared cost nothing at runtime.

Incorrect (redeclaring empty methods):
```php
class OrderObserver extends BaseObserver
{
    public function creating(Model $model): void {}   // unnecessary
    public function created(Model $model): void
    {
        // your logic
    }
    public function updating(Model $model): void {}   // unnecessary
}
```

Correct:
```php
class OrderObserver extends BaseObserver
{
    public function created(Model $model): void
    {
        // your logic
    }
}
```

## Parameter Type Must Be `Model` — Never a Concrete Class

PHP parameter types are contravariant. Narrowing the parent's `Model $model` to `Order $model` is a fatal "Declaration must be compatible" error.

Incorrect (fatal at class load time):
```php
public function created(Order $model): void   // narrower than Model — PHP fatal
{
    // never reached
}
```

Correct:
```php
public function created(Model $model): void
{
    /** @var Order $model */
    $model->doSomething();  // use @var for IDE narrowing, not a type hint
}
```

## Return `false` to Cancel — Not Exceptions

Returning `false` from a before-event method (`creating`, `updating`, `deleting`) cancels the operation cleanly.

```php
public function deleting(Model $model): bool|void
{
    if ($model->has_active_subscription) {
        return false;  // cancels the delete — no exception needed
    }
}
```

## Register in a ServiceProvider — Never in a Controller

```php
// In OrderServiceProvider::boot():
Order::observe(OrderObserver::class);
```
