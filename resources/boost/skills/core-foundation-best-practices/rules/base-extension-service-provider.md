# BaseExtensionServiceProvider Rules

## Use the Structured Hooks, Not Raw Static Calls in `boot()`

`BaseExtensionServiceProvider` replaces a flat wall of static calls with named, typed extension hooks.

Incorrect (raw static calls — no structure, no type safety):
```php
public function boot(): void
{
    Order::addFillable(['subscription_id']);
    Order::addRelation('subscription', fn ($o) => $o->hasOne(Subscription::class));
    UserResource::addField('plan', fn ($u, $r) => $u->subscription?->plan_code);
    OrderService::addPipe('place', ValidateSubscriptionPipe::class);
    FilterApplicator::addOperator(new BetweenDateOperator);
}
```

Correct (structured hooks):
```php
class SubscriptionServiceProvider extends BaseExtensionServiceProvider
{
    protected function extendModels(): void
    {
        $this->model(Order::class)
            ->fillable(['subscription_id', 'plan_code'])
            ->casts(['subscription_id' => 'integer', 'renews_at' => 'datetime'])
            ->relation('subscription', fn ($o) => $o->hasOne(Subscription::class))
            ->searchable(['subscription_id']);
    }

    protected function extendResources(): void
    {
        $this->resource(UserResource::class)
            ->field('plan', fn ($u, $r) => $u->subscription?->plan_code)
            ->remove('raw_plan_data');
    }

    protected function extendServices(): void
    {
        $this->service(OrderService::class)
            ->pipe('place', ValidateSubscriptionPipe::class)
            ->prefer(SubscriptionOrderService::class, fn () => config('subscription.active'));
    }

    protected function extendOperators(): void
    {
        $this->operator(new BetweenDateOperator);
    }
}
```

## Five Extension Hooks

| Hook | Purpose |
|---|---|
| `registerBindings()` | Container bindings — runs during `register()`, before any `boot()` |
| `extendModels()` | Add fillable, casts, relations, scopes, searchable to `BaseModel` subclasses |
| `extendResources()` | Add, override, or remove fields on `BaseResource` subclasses |
| `extendServices()` | Add pipes or preference swaps to `BaseService` subclasses |
| `extendOperators()` | Register custom `FilterOperator` implementations |

Override only the hooks you need — all are no-op by default.

## Container Bindings Go in `registerBindings()`, Not `boot()`

`registerBindings()` runs during `register()` — before any `boot()` call. This is the correct phase for `bind()` and `singleton()`.

```php
protected function registerBindings(): void
{
    $this->app->bind(OrderService::class, fn () => (new OrderService)->resolvePreference());
}
```

## Fluent Builders Are Type-Validated

Each factory method (`$this->model()`, `$this->resource()`, `$this->service()`) throws `LogicException` at boot time if you pass a class that does not extend the required base:

- `$this->model(Foo::class)` — throws if `Foo` does not extend `BaseModel`
- `$this->resource(Foo::class)` — throws if `Foo` does not extend `BaseResource`
- `$this->service(Foo::class)` — throws if `Foo` does not extend `BaseService`

This surfaces configuration errors early, at provider registration, not at request time.

## Always Call `parent::boot()` When Overriding `boot()`

The five hooks are called from `BaseExtensionServiceProvider::boot()`. If you override `boot()` without calling `parent::boot()`, none of the hooks run.

Incorrect:
```php
public function boot(): void
{
    $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
    // extendModels(), extendResources(), etc. never run
}
```

Correct:
```php
public function boot(): void
{
    parent::boot(); // runs extendModels, extendResources, extendServices, extendOperators
    $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
}
```

## Module Isolation — Never Edit Another Module's Source

A module that needs to extend another module's model, resource, or service always does so through the extension hooks — never by modifying the source of the other module.

The fluent builders in `extendModels()`, `extendResources()`, and `extendServices()` are the canonical cross-module extension API. Use them from the extending module's ServiceProvider.

## Observer and Policy Registration Still Goes in `boot()` Directly

`BaseExtensionServiceProvider` does not abstract observer or policy registration — do those in `boot()` after calling `parent::boot()`:

```php
public function boot(): void
{
    parent::boot();
    Order::observe(OrderObserver::class);
    Gate::policy(Order::class, OrderPolicy::class);
    $this->loadRoutesFrom(__DIR__ . '/../routes/api.php');
}
```
