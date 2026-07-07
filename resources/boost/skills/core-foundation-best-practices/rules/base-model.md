# BaseModel Best Practices

## Extend Cross-Module Without Editing the Model

All four extension points are registered from a ServiceProvider — the model source is never touched.

```php
// In SubscriptionServiceProvider::boot():
Order::addFillable(['subscription_id', 'plan_code']);
Order::addCast(['renews_at' => 'datetime']);
Order::addRelation('subscription', fn (Order $o) => $o->hasOne(Subscription::class));
Order::addScope(new ActiveSubscriptionScope, 'active_subscription');
Order::addSearchable(['subscription_id']);
```

None of these lines touch `Order.php`.

## `static::class` — Never `self::class`

`self::class` always resolves to the class it was written in, even when called on a subclass. This means all subclasses share the same registry entry — they overwrite each other.

Incorrect:
```php
public static function addFillable(array $fields): void
{
    static::$additionalFillable[self::class] = $fields;  // all subclasses share one key
}
```

Correct:
```php
static::$additionalFillable[static::class] = $fields;
```

## Never Register a Model as a Singleton

Models hold per-row mutable state (attributes, relations, dirty flags). A singleton leaks data from one request to the next.

Incorrect:
```php
$this->app->singleton(Order::class);
```

Correct: don't register models in the container at all. Retrieve them via repositories.

## `BaseModel` Is Recommended, Not Required

`BaseRepository` accepts any Eloquent `Model`. Use `BaseModel` for the full extensibility system. If the model is from a third-party package, implement `HasSearchableColumns` and/or `HasRelationRegistry` to opt in to the specific capabilities you need.
