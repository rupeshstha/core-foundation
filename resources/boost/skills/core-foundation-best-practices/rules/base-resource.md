# BaseResource & BaseCollection Best Practices

## Override `fields()` — Never `toArray()`

`toArray()` is `final` — it drives the modular field pipeline. Overriding it disables `addField()` / `removeField()` for the entire codebase.

Incorrect:
```php
public function toArray(Request $request): array
{
    return ['id' => $this->id, 'status' => $this->status];
}
```

Correct:
```php
protected function fields(Request $request): array
{
    return ['id' => $this->id, 'status' => $this->status];
}
```

## Extend from a ServiceProvider — Never Edit Another Module's Resource

`removeField()` always runs last — it wins regardless of registration order.

```php
// In PrivacyServiceProvider::boot():
UserResource::removeField('email');   // strips PII for this region

// In AnalyticsServiceProvider::boot():
UserResource::addField('segment', fn ($user) => $user->segment_id);
```

## `BaseCollection` — Declare `$collects` Without a Type Annotation

`ResourceCollection::$collects` is untyped in Laravel. Adding a type annotation in a subclass is a fatal PHP error.

Incorrect:
```php
class OrderCollection extends BaseCollection
{
    public string $collects = OrderResource::class;  // fatal: cannot add type to untyped parent
}
```

Correct:
```php
class OrderCollection extends BaseCollection
{
    // No type annotation — ResourceCollection::$collects is untyped in Laravel.
    public $collects = OrderResource::class;
}
```

## Use `withTimestamps()` on Model-Backed Resources

```php
protected function fields(Request $request): array
{
    return $this->withTimestamps([
        'id'     => $this->id,
        'status' => $this->status,
    ]);
    // Appends 'created_at' and 'updated_at' in ISO 8601 format
}
```
