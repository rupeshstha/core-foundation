# BaseObserver Rules

## Override Only What You Need

All lifecycle methods are no-op by default. Methods you do not override cost nothing at runtime — they do not need to be declared at all.

```php
use CoreFoundation\Observers\BaseObserver;

class OrderObserver extends BaseObserver
{
    public function created(mixed $model): void
    {
        // runs after the order is first persisted
    }

    public function deleting(mixed $model): bool
    {
        return $model->canBeDeleted(); // false cancels the deletion
    }

    // updating, updated, saving, saved, deleted, etc. not declared = no-op
}
```

## Cancelling an Operation — Return `false` from Before-Events

Return `false` from any before-event to cancel the operation. Returning `void` (no return) never cancels.

| Before-event | Cancellable | After-event (not cancellable) |
|---|---|---|
| `creating` | Yes | `created` |
| `updating` | Yes | `updated` |
| `saving` | Yes | `saved` |
| `deleting` | Yes | `deleted` |
| `restoring` | Yes | `restored` |
| `forceDeleting` | Yes | `forceDeleted` |

```php
public function deleting(mixed $model): bool
{
    if ($model->items()->exists()) {
        return false; // cancel — order has items
    }
}

public function creating(mixed $model): void
{
    // void return — never cancels, even if you add logic here
}
```

## Registration — Always in ServiceProvider `boot()`

```php
public function boot(): void
{
    parent::boot();
    Order::observe(OrderObserver::class);
    Order::observe(RepositoryCacheObserver::class); // automatic cache busting
}
```

Multiple observers on the same model run in registration order.

## IDE Type Safety — PHPDoc Generics

Use `@extends` in a PHPDoc block (not `@@extends` — that is only for Blade templates):

```php
/**
 * @extends BaseObserver<\App\Models\Order>
 */
class OrderObserver extends BaseObserver
{
    public function created(mixed $model): void
    {
        /** @var \App\Models\Order $model */
        // IDE now infers $model is Order
    }
}
```

Note: in Blade template code examples, write `@@extends` to avoid Blade compiling it as a layout directive.

## Soft-Delete Events — Override Only if Model Uses SoftDeletes

Only override `restoring`, `restored`, `forceDeleting`, `forceDeleted` when the model has the `SoftDeletes` trait. They are no-ops on hard-delete models.

## Keep Observers Thin

Observers are lifecycle hooks, not business logic containers. Avoid putting significant business logic in observers.

Incorrect (business logic in observer):
```php
public function created(mixed $model): void
{
    // Heavy business logic, API calls, etc.
    $this->paymentService->charge($model->user_id, $model->total);
    Mail::to($model->user)->send(new OrderConfirmation($model));
}
```

Correct (dispatch event or job):
```php
public function created(mixed $model): void
{
    OrderCreatedJob::dispatch($model->id);
    // or: event(new OrderCreated($model));
}
```
