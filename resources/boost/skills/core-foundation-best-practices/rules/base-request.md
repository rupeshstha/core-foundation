# BaseRequest Best Practices

## Never Override `rules()` — It Is `final`

`rules()` assembles the full rule set by merging `baseRules()`, `storeRules()`, and `updateRules()` based on the HTTP method. Overriding it breaks the merge.

Incorrect:
```php
public function rules(): array
{
    return ['name' => 'required'];  // bypasses the merge hierarchy
}
```

Correct:
```php
protected function baseRules(): array
{
    return ['name' => ['required', 'string', 'max:255']];
}

protected function storeRules(): array
{
    return ['email' => ['required', 'email', 'unique:users']];
}

protected function updateRules(): array
{
    return ['email' => ['sometimes', 'email', Rule::unique('users')->ignore($this->routeModel())]];
}
```

## Later Key Wins in the Merge

`storeRules()` and `updateRules()` merge over `baseRules()`. Use this to relax `required` to `sometimes` on update without repeating every rule.

```php
protected function baseRules(): array
{
    return ['name' => 'required|string', 'email' => 'required|email'];
}

protected function updateRules(): array
{
    return ['name' => 'sometimes|string', 'email' => 'sometimes|email'];
    // On PUT/PATCH: name and email become optional — base rules overwritten
}
```

## Always `$request->validated()` — Never `$request->all()`

`$request->all()` returns unvalidated data including any extra keys the client sent.

Incorrect:
```php
$order = $this->service->create($request->all());
```

Correct:
```php
$order = $this->service->create($request->validated());
```

## Include Route Parameters via `mergeRouteParameters()`

Route params (`{order}`) are not in the request body. Use `prepareForValidation()` to merge them so they appear in `validated()`.

```php
protected function prepareForValidation(): void
{
    $this->merge(['order_id' => $this->route('order')]);
}
```
