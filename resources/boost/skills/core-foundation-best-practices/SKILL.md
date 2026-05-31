---
name: core-foundation-best-practices
description: "Apply this skill whenever writing, reviewing, or refactoring code in a CoreFoundation Laravel project. Triggers for all base class usage: BaseController (response envelope, exception handling), BaseService (pipeline, events, defer), BaseRepository (filtering, caching, query contracts, locking), BaseDataObject (DTOs, typed properties), BaseResource/BaseCollection (field pipeline, modular extension), BaseRequest (rule hierarchy, route params), BasePolicy (deny-by-default), BaseObserver (lifecycle events), BaseJob (notifications, batching), BaseApiException (three-layer exception system), BaseExtensionServiceProvider (module hooks), BaseTestCase (envelope assertions), BaseModel (modular extensibility), and ApplicationContext (domain-scoped state). Also use for module isolation decisions, the response envelope shape, and any CoreFoundation architecture question."
license: MIT
metadata:
  author: Rupesh Shrestha
---

# CoreFoundation Best Practices

Best practices for CoreFoundation — the enterprise Laravel API foundation. Each rule teaches the correct pattern and why. Check sibling files for established conventions before applying any rule.

## Consistency First

Before applying any rule, check what the application already does. If a pattern exists, follow it — don't introduce a second way. These rules are defaults for when no pattern exists yet, not overrides.

## Quick Reference

### 1. BaseController → `rules/base-controller.md`

- Extend `BaseController` for all API controllers
- Use `successResponse()`, `createdResponse()`, `paginatedResponse()`, `noContentResponse()` — never `response()->json()`
- Wrap service/repository calls in `try/catch (Throwable $e)` → `handleException($e)`
- Never `catch (SpecificException)` in a controller — put `render()` on the exception class
- Use `$this->lang('key')` for all user-facing messages
- Authorization via `$this->authorize()` — policy handles it, not the controller

### 2. BaseService → `rules/base-service.md`

- Services return `BaseDataObject`, never `JsonResponse`, never a raw model, never a plain array
- `HasPipeline` for execution hooks that can modify data; `HasEvent` for fire-and-forget side effects
- Register pipes from a ServiceProvider, never inside the service class
- `defer()` for post-response non-critical work; `dispatch('event')` for immediate pub/sub; `->onQueue()` for critical async
- `static::class` in all static registries — never `self::class`

### 3. BaseRepository → `rules/base-repository.md`

- Implement only the contract interfaces you need (`ReadRepositoryContract`, `WriteRepositoryContract`, `QueryRepositoryContract`, or all three via `RepositoryContract`)
- `searchable()` is the SQL-injection guard — columns not listed are silently ignored by the filter system
- Custom queries always start from `$this->query()` — never `Model::query()` directly
- Always `bind()` repositories — never `singleton()` or `scoped()`
- `fetchAll` and `fetchById` are cached by default; override `cachedMethods()` to change
- Use `lockForUpdate()` / `sharedLock()` for pessimistic locking (automatically bypasses cache)
- Use `updateAtomic($id, $attributes, $conditions)` for Compare-and-Swap concurrency control

### 4. BaseDataObject → `rules/base-data-object.md`

- Pattern A: `BaseDataObject::fromArray($array)` for one-off results
- Pattern B: typed `readonly` properties + `#[ApiResponse]` / `#[Property]` for stable shared shapes
- Always call `parent::__construct([...])` in typed DTOs to populate the Fluent bag
- Namespace is `CoreFoundation\Manipulators\BaseDataObject` — not `CoreFoundation\DataObjects`
- `BaseDataObject` is the output side only — input validation belongs to `BaseRequest`

### 5. BaseResource & BaseCollection → `rules/base-resource.md`

- Override `fields()`, never `toArray()` — the pipeline is `final`
- `addField()` / `removeField()` from a ServiceProvider; never edit another module's resource source
- `removeField()` always wins — applied last regardless of `addField()` order
- Always declare `public string $collects` on `BaseCollection` — no naming-convention magic
- `$wrap = null` on both — the envelope belongs to `BaseController`

### 6. BaseRequest → `rules/base-request.md`

- `rules()` is `final` — override `baseRules()`, `storeRules()`, `updateRules()` only
- `storeRules()` / `updateRules()` merge over `baseRules()` — later key wins, use to relax `required` to `sometimes`
- Always use `$request->validated()` — never `$request->all()`
- `mergeRouteParameters()` in `prepareForValidation()` to make route params available in validated data
- `routeModel()` in `updateRules()` for `Rule::unique()->ignore()`

### 7. BasePolicy → `rules/base-policy.md`

- All abilities return `Response::deny()` by default — not overriding = denied
- Override only the abilities you intend to grant — an omission is a security gate, not an open gate
- Register via `Gate::policy()` in a ServiceProvider's `boot()` — never in a controller
- Use `$this->authorize()` in controllers — the ExceptionRenderer converts `AuthorizationException` to 403 JSON automatically

### 8. BaseObserver → `rules/base-observer.md`

- All lifecycle methods are no-op by default — override only what you need
- Return `false` from a before-event method (`creating`, `updating`, `deleting`, etc.) to cancel the operation
- Register via `Model::observe()` in a ServiceProvider's `boot()`
- `@@extends` in PHPDoc generics in Blade templates to avoid Blade directive collision

### 9. BaseJob → `rules/base-job.md`

- Override `shouldNotify()` → `true` to enable notification dispatch on failure
- `notifyStarted()` / `notifyCompleted()` are opt-in — call manually inside `handle()`
- `failed()` is handled automatically: rolls back transactions, logs, notifies — never override it; override `errorContext()` instead
- `SkipIfBatchCancelled` is prepended automatically — do not add it to `bindMiddlewares()`

### 10. BaseApiException → `rules/base-exception.md`

- Three layers: ExceptionRenderer (Layer 1) → BaseApiException (Layer 2) → handleException() (Layer 3)
- Implement `ShouldntReport` for known, client-fixable exceptions — keeps logs clean
- `exception_id` (UUID) only in Layer 3 fatal responses — never on domain exceptions
- Override `context()` to add domain-specific searchable fields to log entries

### 11. BaseExtensionServiceProvider → `rules/base-extension-service-provider.md`

- Five structured hooks: `registerBindings()`, `extendModels()`, `extendResources()`, `extendServices()`, `extendOperators()`
- Always call `parent::boot()` first when overriding `boot()`
- Module B never edits Module A's source — all cross-module extension via ServiceProvider hooks
- `static::class` in static registries — never `self::class`

### 12. BaseTestCase → `rules/base-test-case.md`

- Use envelope assertion helpers (`assertSuccessResponse`, `assertValidationError`, `assertPaginatedResponse`, etc.) — never raw `assertStatus()` + `assertJson()`
- Envelope key is `payload`, not `data`
- Always `array_merge(parent::defaultHeaders(), [...])` when overriding `defaultHeaders()`
- `RefreshDatabase` for integration tests that spawn workers/observers; `DatabaseTransactions` for most HTTP feature tests

### 13. BaseModel → `rules/base-model.md`

- Module B adds fillable/casts/relations/scopes from its own ServiceProvider — never edit the model source
- `static::class` in all static registries — never `self::class` (subclass collision)
- Never register a model as `singleton()` or `scoped()` — always transient
- `BaseRepository` requires the model to extend `BaseModel` — never `Illuminate\Database\Eloquent\Model`

## How to Apply

Always use a sub-agent to read the relevant rule file(s) before writing or reviewing code.

1. Identify which base class(es) are involved
2. Read the corresponding rule file(s) from `rules/`
3. Check sibling files for existing patterns — follow those first per Consistency First
4. Apply the rules from the most specific file
