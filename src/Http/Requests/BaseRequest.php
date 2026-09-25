<?php

namespace CoreFoundation\Http\Requests;

use ReflectionClass;
use Illuminate\Validation\Rule;
use CoreFoundation\Support\Lang;
use CoreFoundation\Attributes\BodyParam;
use CoreFoundation\Attributes\ApiRequest;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * BaseRequest
 *
 * Foundation request class for all API form requests.
 * Extends Laravel's FormRequest — everything Laravel documents works as-is.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ GUIDELINE — HOW TO BUILD YOUR REQUEST CLASSES                               │
 * │                                                                             │
 * │ This base gives you three rule methods. Override the ones you need:         │
 * │                                                                             │
 * │   baseRules()    — rules shared between Store and Update                    │
 * │   storeRules()   — rules added/overridden only on POST (store)              │
 * │   updateRules()  — rules added/overridden only on PUT/PATCH (update)        │
 * │                                                                             │
 * │ The base merges them automatically in the correct order:                    │
 * │   Store  → baseRules() + storeRules()                                       │
 * │   Update → baseRules() + updateRules()                                      │
 * │                                                                             │
 * │ PATTERN A — One class for both Store and Update (recommended for simple     │
 * │             resources where most rules are shared):                         │
 * │                                                                             │
 * │   class UpsertUserRequest extends BaseRequest                               │
 * │   {                                                                         │
 * │       protected function baseRules(): array                                 │
 * │       {                                                                     │
 * │           return [                                                          │
 * │               'name'  => ['required', 'string', 'max:255'],                 │
 * │               'email' => ['required', 'email'],                             │
 * │           ];                                                                │
 * │       }                                                                     │
 * │                                                                             │
 * │       protected function storeRules(): array                                │
 * │       {                                                                     │
 * │           return [                                                          │
 * │               'password' => ['required', 'string', 'min:8', 'confirmed'],   │
 * │           ];                                                                │
 * │       }                                                                     │
 * │                                                                             │
 * │       protected function updateRules(): array                               │
 * │       {                                                                     │
 * │           return [                                                          │
 * │               'name'     => ['sometimes', 'string', 'max:255'],             │
 * │               'password' => ['sometimes', 'string', 'min:8', 'confirmed'],  │
 * │           ];                                                                │
 * │       }                                                                     │
 * │   }                                                                         │
 * │                                                                             │
 * │ PATTERN B — Separate classes that both extend BaseRequest (recommended for  │
 * │             complex resources where Store and Update diverge significantly): │
 * │                                                                             │
 * │   class StoreUserRequest extends BaseRequest                                │
 * │   {                                                                         │
 * │       protected function baseRules(): array { ... }                         │
 * │   }                                                                         │
 * │                                                                             │
 * │   class UpdateUserRequest extends BaseRequest                               │
 * │   {                                                                         │
 * │       protected function baseRules(): array { ... }                         │
 * │       protected function updateRules(): array { ... }                       │
 * │   }                                                                         │
 * │                                                                             │
 * │ NEVER override rules() directly — the merge logic lives there.              │
 * │ Override baseRules(), storeRules(), updateRules() instead.                  │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ AUTHORIZATION                                                               │
 * │                                                                             │
 * │ Returns true by default — authorization is handled at the middleware or     │
 * │ policy layer. Override in your request class if you need request-level      │
 * │ authorization logic:                                                        │
 * │                                                                             │
 * │   public function authorize(): bool                                         │
 * │   {                                                                         │
 * │       return $this->user()->can('update', $this->route('post'));            │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ ROUTE PARAMETER MERGING (optional)                                          │
 * │                                                                             │
 * │ Call $this->mergeRouteParameters() inside prepareForValidation() when you   │
 * │ need route parameters available in validated data or rules:                 │
 * │                                                                             │
 * │   protected function prepareForValidation(): void                           │
 * │   {                                                                         │
 * │       $this->mergeRouteParameters(['organization']);                        │
 * │       // Now $this->validated()['organization'] === route {organization}    │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
abstract class BaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Return a JSON 403 on authorization failure.
     *
     * FormRequest's default throws a redirect-based response which is wrong
     * for APIs. This ensures authorization failures are always JSON, consistent
     * with how Laravel already handles validation failures for JSON requests.
     *
     * Do not override this — handle authorization logic inside authorize()
     * or at the middleware / policy layer instead.
     */
    final protected function failedAuthorization(): never
    {
        throw new HttpResponseException(
            response()->json([
                'message' => Lang::get('core-foundation::http.unauthorized'),
            ], 403)
        );
    }

    /**
     * Merges baseRules() with the correct lifecycle rules based on HTTP method.
     *
     * Store  (POST)       → baseRules() merged with storeRules()
     * Update (PUT/PATCH)  → baseRules() merged with updateRules()
     * Other              → baseRules() only
     *
     * Later keys win — storeRules()/updateRules() intentionally override
     * baseRules() when the same key appears in both, allowing update requests
     * to relax a 'required' to 'sometimes' without duplicating the full rule.
     *
     * NEVER override this method. Use baseRules(), storeRules(), updateRules().
     */
    final public function rules(): array
    {
        return match (true) {
            $this->isStoring() => array_merge($this->baseRules(), $this->storeRules()),
            $this->isUpdating() => array_merge($this->baseRules(), $this->updateRules()),
            default => $this->baseRules(),
        };
    }

    /**
     * Rules shared between Store and Update.
     *
     * Define all field rules here first. Use storeRules() / updateRules()
     * to add new fields or override specific rules for each lifecycle.
     */
    protected function baseRules(): array
    {
        return [];
    }

    /**
     * Rules applied only on Store (POST).
     *
     * Keys here are merged over baseRules() — same key in both means this wins.
     * Use for: required-only-on-create fields, confirmed passwords, etc.
     */
    protected function storeRules(): array
    {
        return [];
    }

    /**
     * Rules applied only on Update (PUT / PATCH).
     *
     * Keys here are merged over baseRules() — same key in both means this wins.
     * Use for: relaxing 'required' to 'sometimes', unique ignore, etc.
     */
    protected function updateRules(): array
    {
        return [];
    }

    /**
     * True when the request is a POST (store / create).
     */
    final protected function isStoring(): bool
    {
        return $this->isMethod('POST');
    }

    /**
     * True when the request is a PUT or PATCH (update).
     */
    final protected function isUpdating(): bool
    {
        return $this->isMethod('PUT') || $this->isMethod('PATCH');
    }

    /**
     * Merge specific route parameters into the request input so they become
     * available in validated data and can be referenced in rules.
     *
     * Call this from prepareForValidation() in your child request:
     *
     *   protected function prepareForValidation(): void
     *   {
     *       $this->mergeRouteParameters(['organization', 'team']);
     *   }
     *
     * Route models are resolved to their route key (typically their ID).
     * Raw scalar route values are merged as-is.
     *
     * @param  array<string>  $parameters  Route parameter names to merge
     */
    final protected function mergeRouteParameters(array $parameters): void
    {
        $values = [];

        foreach ($parameters as $parameter) {
            $value = $this->route($parameter);

            if ($value === null) {
                continue;
            }

            // If the route parameter is a model, resolve to its route key value
            // so the merged value is a scalar (e.g. int ID) not an object.
            $values[$parameter] = is_object($value) && method_exists($value, 'getRouteKey')
                ? $value->getRouteKey()
                : $value;
        }

        $this->merge($values);
    }

    /**
     * Retrieve a route-bound model or scalar value by parameter name.
     *
     * Convenience wrapper around $this->route() with a clear name,
     * useful when writing updateRules() that reference the bound model:
     *
     *   protected function updateRules(): array
     *   {
     *       return [
     *           'email' => [
     *               'sometimes', 'email',
     *               Rule::unique('users', 'email')->ignore($this->routeModel('user')),
     *           ],
     *       ];
     *   }
     */
    final protected function routeModel(string $parameter): mixed
    {
        return $this->route($parameter);
    }

    /**
     * Describe the request body fields for future API doc generation.
     *
     * Override this and return one BodyParam per field, mirroring what you
     * define in baseRules() / storeRules() / updateRules() so docs stay in
     * sync with validation without a separate spec file.
     *
     * Works alongside the class-level #[ApiRequest] attribute:
     *
     *   #[ApiRequest(description: 'Place a new order.', tags: ['Orders'])]
     *   class UpsertOrderRequest extends BaseRequest
     *   {
     *       protected function baseRules(): array
     *       {
     *           return [
     *               'product'  => ['required', 'string'],
     *               'quantity' => ['required', 'integer', 'min:1'],
     *               'currency' => ['sometimes', 'string'],
     *           ];
     *       }
     *
     *       public function schema(): array
     *       {
     *           return [
     *               BodyParam::make('product')
     *                   ->type('string')
     *                   ->description('The product SKU.')
     *                   ->example('SKU-001')
     *                   ->required(),
     *
     *               BodyParam::make('quantity')
     *                   ->type('integer')
     *                   ->description('Units to order.')
     *                   ->example(2)
     *                   ->required()
     *                   ->minimum(1),
     *
     *               BodyParam::make('currency')
     *                   ->type('string')
     *                   ->description('ISO 4217 currency code.')
     *                   ->example('USD')
     *                   ->optional()
     *                   ->enum(['USD', 'EUR', 'GBP']),
     *           ];
     *       }
     *   }
     *
     * @return array<BodyParam>
     *
     * @deprecated Scramble infers request schemas from validation rules automatically.
     *   Remove this method from your request classes — no replacement needed.
     */
    public function schema(): array
    {
        return [];
    }

    /**
     * Read the #[ApiRequest] attribute from this class, if present.
     *
     * @deprecated Used only by GenerateApiDocs which is deprecated.
     *   Use dedoc/scramble instead — no manual attribute reading needed.
     */
    public static function getRequestMeta(): ?ApiRequest
    {
        $reflection = new ReflectionClass(static::class);
        $attributes = $reflection->getAttributes(ApiRequest::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }
}
