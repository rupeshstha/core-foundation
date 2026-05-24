# BaseModel Rules

## Every Eloquent Model Must Extend BaseModel

`BaseRepository` requires a `BaseModel` subclass. Passing a plain `Model` throws a `LogicException` at construction time.

```php
use CoreFoundation\Entities\BaseModel;

class Order extends BaseModel
{
    protected $fillable = ['user_id', 'total', 'currency', 'status'];

    protected $casts = [
        'total'      => 'decimal:2',
        'created_at' => 'datetime',
    ];
}
```

## Modular Extension — Never Edit Another Module's Model

Module B never adds columns, casts, or relations to Module A's model source. All additions are registered from Module B's ServiceProvider.

Incorrect (Module B edits Module A's source):
```php
// Inside Order model:
protected $fillable = ['user_id', 'total', 'subscription_id']; // subscription_id is wrong here
```

Correct (Module B registers from its own ServiceProvider):
```php
// In SubscriptionServiceProvider::boot():
Order::addFillable(['subscription_id', 'plan_code']);
Order::addCast(['subscription_id' => 'integer', 'renews_at' => 'datetime']);
Order::addRelation('subscription', fn (Order $o) => $o->hasOne(Subscription::class));
Order::addScope(new ActiveSubscriptionScope);
Order::addSearchable(['subscription_id']);
```

## Always Use `static::class`, Never `self::class`

All static registries are keyed by `static::class`. Using `self::class` would incorrectly key registrations to the trait or base class instead of the concrete model.

Incorrect:
```php
public static function addFillable(array $fields): void
{
    self::$additionalFillable[self::class][] = $fields; // always resolves to the trait/base
}
```

Correct (already done inside the trait — this is why you must never override these methods):
```php
static::$additionalFillable[static::class][] = $fields; // resolves to Order::class
```

## The Four Modular Extension APIs

| API | What it adds |
|---|---|
| `Order::addFillable(['col'])` | Additional mass-assignable columns |
| `Order::addCast(['col' => 'type'])` | Additional Eloquent casts |
| `Order::addRelation('name', fn ($o) => ...)` | Dynamic Eloquent relations |
| `Order::addScope(new ScopeClass)` | Additional global scopes |
| `Order::addSearchable(['col'])` | Columns searchable via `BaseRepository` |

## Introspection

Use these read-only methods to inspect registered extensions (e.g., in tests):

```php
Order::getAdditionalFillable();   // fields added by modules
Order::getAdditionalCasts();      // casts added by modules
Order::getBindRelations();        // relations added by modules
Order::getAdditionalScopes();     // global scopes added by modules
```

## Primary Key — No Opinion, Declare Explicitly

`BaseModel` does not set a primary key type. Declare it on the concrete model:

```php
// Auto-increment integer (Laravel default — nothing to declare)
class Order extends BaseModel {}

// UUID string
class Order extends BaseModel
{
    use HasUuids;

    protected $keyType    = 'string';
    public    $incrementing = false;
}
```

## Soft Deletes — Opt In on the Concrete Model

`BaseModel` does not include `SoftDeletes`. Add it only to models that need it:

```php
class Order extends BaseModel
{
    use SoftDeletes;
}
```

Only override `restore`, `forceDelete`, `restoring`, `restored`, `forceDeleting`, `forceDeleted` in observers and policies when the model uses `SoftDeletes`.

## Registration — Bind with `bind()`, Never `singleton()`

Repositories bound to a `BaseModel` must use `bind()` in the ServiceProvider so each resolution gets a fresh instance:

```php
// In ServiceProvider::register():
$this->app->bind(OrderRepository::class);
```

Never use `singleton()` — a singleton repository carries state (active query, applied filters) across requests in long-running processes (Octane, queue workers).
