# BaseRequest Rules

## Never Override `rules()` — It Is `final`

`rules()` assembles the correct rule set automatically based on the HTTP method. Override the scoped methods only.

Incorrect:
```php
public function rules(): array
{
    return ['total' => ['required', 'numeric']];
}
```

Correct — override `baseRules()`, `storeRules()`, or `updateRules()`:
```php
protected function baseRules(): array
{
    return ['total' => ['required', 'numeric', 'min:0.01']];
}
```

## Rule Method Hierarchy

| Method | When applied | Purpose |
|---|---|---|
| `baseRules()` | All HTTP methods | Shared rules — define everything here first |
| `storeRules()` | POST only | Merged over `baseRules()` — later key wins |
| `updateRules()` | PUT / PATCH only | Merged over `baseRules()` — later key wins |

Later key wins means `updateRules()` can relax a `required` to `sometimes` without duplicating the full rule.

## Pattern A — One Class for Store and Update

Use when the Store/Update difference is small:

```php
class UpsertOrderRequest extends BaseRequest
{
    protected function baseRules(): array
    {
        return [
            'total'    => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }

    protected function storeRules(): array
    {
        return [
            'user_id' => ['required', 'integer', 'exists:users,id'],
        ];
    }

    protected function updateRules(): array
    {
        return [
            'total'    => ['sometimes', 'numeric', 'min:0.01'], // relaxed from required
            'currency' => ['sometimes', 'string', 'size:3'],
            'status'   => ['sometimes', 'string', 'in:pending,confirmed,cancelled'],
        ];
    }
}
```

## Pattern B — Separate Store and Update Classes

Use when Store and Update diverge significantly:

```php
class StoreOrderRequest extends BaseRequest
{
    protected function baseRules(): array
    {
        return ['total' => ['required', 'numeric', 'min:0.01']];
    }

    protected function storeRules(): array
    {
        return ['user_id' => ['required', 'integer', 'exists:users,id']];
    }
}

class UpdateOrderRequest extends BaseRequest
{
    protected function updateRules(): array
    {
        return [
            'total'  => ['sometimes', 'numeric', 'min:0.01'],
            'status' => ['sometimes', 'in:pending,confirmed,cancelled'],
        ];
    }
}
```

## Always Use `$request->validated()`

Never use `$request->all()` or `$request->input()` for data that goes to a service.

Incorrect:
```php
$result = $this->service->place($request->all());
```

Correct:
```php
$result = $this->service->place($request->validated());
```

## Route Parameter Merging

Call `mergeRouteParameters()` in `prepareForValidation()` to make route params available in `validated()` and in rules:

```php
protected function prepareForValidation(): void
{
    $this->mergeRouteParameters(['organization', 'team']);
    // $this->validated()['organization'] now contains the route {organization} value
}
```

## `routeModel()` for `unique()->ignore()`

Use `routeModel()` in `updateRules()` to reference the bound model:

```php
protected function updateRules(): array
{
    return [
        'email' => [
            'sometimes', 'email',
            Rule::unique('users', 'email')->ignore($this->routeModel('user')),
        ],
    ];
}
```

## Authorization

`authorize()` returns `true` by default. Override only when you need request-level authorization (prefer policy layer):

```php
public function authorize(): bool
{
    return $this->user()->can('update', $this->route('order'));
}
```

Failed authorization always returns JSON 403 — `failedAuthorization()` is `final` and handles this automatically. Never override it.

## API Doc Schema (Optional)

```php
use CoreFoundation\Attributes\ApiRequest;
use CoreFoundation\Attributes\BodyParam;

#[ApiRequest(description: 'Place a new order.', tags: ['Orders'])]
class StoreOrderRequest extends BaseRequest
{
    public function schema(): array
    {
        return [
            BodyParam::make('total')
                ->type('number')
                ->description('Order total.')
                ->example(99.99)
                ->required()
                ->minimum(0.01),
        ];
    }
}
```
