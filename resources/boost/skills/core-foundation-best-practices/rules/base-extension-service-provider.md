# BaseExtensionServiceProvider Best Practices

## Module B Never Edits Module A's Source

In a modular monolith, modules must not touch each other's source files. All cross-module extension is registered from a ServiceProvider.

Incorrect:
```php
// In subscription/Models/Order.php — editing the order module's source directly
protected $fillable = ['subscription_id'];  // breaks module isolation
```

Correct:
```php
// In SubscriptionServiceProvider::boot()
protected function extendModels(): void
{
    $this->model(Order::class)
        ->fillable(['subscription_id'])
        ->cast(['subscription_id' => 'integer'])
        ->relation('subscription', fn (Order $o) => $o->hasOne(Subscription::class));
}
```

## Use Structured Hooks — Not Raw Static Calls in `boot()`

The structured hooks produce readable, validated, IDE-friendly registrations. Raw static calls in `boot()` compile the same but lose the type safety and discoverability.

Incorrect:
```php
public function boot(): void
{
    parent::boot();
    Order::addFillable(['subscription_id']);
    OrderResource::addField('plan', fn ($o) => $o->subscription?->plan);
    OrderService::addPipe('create', ValidateSubscriptionPipe::class);
}
```

Correct:
```php
protected function extendModels(): void
{
    $this->model(Order::class)->fillable(['subscription_id']);
}

protected function extendResources(): void
{
    $this->resource(OrderResource::class)->field('plan', fn ($o) => $o->subscription?->plan);
}

protected function extendServices(): void
{
    $this->service(OrderService::class)->pipe('create', ValidateSubscriptionPipe::class);
}
```

## Always Call `parent::boot()` First

`parent::boot()` invokes all five structured hooks. Skipping it means none of your extensions run.

```php
public function boot(): void
{
    parent::boot();  // MUST be first
    // custom boot logic here
}
```
