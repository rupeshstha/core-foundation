---
name: core-foundation-best-practices
description: "Apply this skill whenever writing, reviewing, or refactoring code in a CoreFoundation Laravel project. Triggers for all base class usage: BaseController (response envelope, exception handling), BaseService (pipeline, events, defer), BaseRepository (filtering, caching, query contracts, locking, repository interfaces, silent updates), BaseDataObject (DTOs, typed properties), BaseResource/BaseCollection (field pipeline, modular extension), BaseRequest (rule hierarchy, route params), BasePolicy (deny-by-default), BaseObserver (lifecycle events), BaseJob (notifications, batching), BaseApiException (three-layer exception system), BaseExtensionServiceProvider (module hooks), BaseTestCase (envelope assertions), BaseModel (modular extensibility, FluentJsonCast), and ApplicationContext (domain-scoped state). Also triggers for cross-cutting coding standards: repository interface + bind pattern, no direct model queries outside repositories, translation keys for every user-facing message, Throwable vs Exception at safety-net catch layers, and variable naming (no one-letter variables like $e). Also use for module isolation decisions, the response envelope shape, and any CoreFoundation architecture question."
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

- Use `successResponse()`, `createdResponse()`, `paginatedResponse()`, `noContentResponse()` — never `response()->json()`
- Wrap every service call: `try { ... } catch (Throwable $exception) { return $this->handleException($exception); }` — `Throwable`, never `Exception`; the variable is never `$e`
- Never catch a specific exception in a controller — put `render()` on the exception class
- Never query a model directly in a controller — call a service method; if a custom query is unavoidable, it must go through a repository's cached method, not a bare `Model::query()`
- Use `$this->lang('key')` for user-facing messages — never hardcode strings; `core:make` generates the matching `lang/en/{name}.php` automatically
- Use `$this->authorize()` — never check permissions manually in the controller body

### 2. BaseService → `rules/base-service.md`

- Always return `BaseDataObject` — never `JsonResponse`, never a raw Eloquent model, never a plain array
- Use `HasPipeline` (`throughPipes()`) for execution hooks that can modify data
- Use class-based events (`SomeEvent::dispatch()`) for fire-and-forget pub/sub — never string-keyed events
- Register pipes from a ServiceProvider, never inside the service class itself
- `static::class` in all static registries — never `self::class`

### 3. BaseRepository → `rules/base-repository.md`

- Every repository is generated as a pair — `{Name}RepositoryContract` (interface) + `{Name}Repository` (concrete) — bound in `ServiceProvider::registerBindings()`; consumers type-hint the interface, never the concrete class
- Always `bind()` — never `singleton()` or `scoped()`; repositories hold per-request state
- `searchable()` is the SQL-injection guard — columns not listed are silently skipped
- `query()` is `protected` — reachable only from named methods inside the concrete repository, never from a service/controller; custom queries always start from `$this->query()`, never `Model::query()` directly
- `fetchAll` and `fetchById` are cached by default; override `cachedMethods()` to change
- Override `cacheScope()` for tenant-scoped entities — cache isolation is not automatic
- `update(..., quiet: true)` skips cache flush and model events (same `Model::withoutEvents()` mechanism Eloquent's own `updateQuietly()` uses) — only for columns nothing cached or observed depends on

### 4. BaseDataObject → `rules/base-data-object.md`

- Namespace is `CoreFoundation\Manipulators\BaseDataObject` — not `CoreFoundation\DataObjects`
- Output side only — input validation belongs to `BaseRequest`
- Pattern A: `BaseDataObject::fromArray($array)` for one-off results
- Pattern B: `readonly` typed properties + `parent::__construct([...])` for stable shared shapes
- Add `#[ApiResponse]` and `#[Property]` on typed DTOs — `php artisan api:docs` reads them

### 5. BaseResource & BaseCollection → `rules/base-resource.md`

- Override `fields()`, never `toArray()` — the pipeline is `final`
- `addField()` / `removeField()` from a ServiceProvider — never edit another module's resource source
- `removeField()` always wins — applied last regardless of `addField()` registration order
- `BaseCollection`: declare `public $collects = YourResource::class` — no type annotation (PHP fatal otherwise), no naming-convention magic

### 6. BaseRequest → `rules/base-request.md`

- `rules()` is `final` — override `baseRules()`, `storeRules()`, or `updateRules()` only
- `storeRules()` / `updateRules()` merge over `baseRules()` — later key wins; use to relax `required` → `sometimes`
- Always `$request->validated()` in controllers — never `$request->all()`
- Use `mergeRouteParameters()` in `prepareForValidation()` to include route params in validated data

### 7. BasePolicy → `rules/base-policy.md`

- All abilities return `Response::deny()` by default — an omission is a security gate, not an open gate
- Override only the abilities you intend to grant
- Register via `Gate::policy()` in a ServiceProvider's `boot()` — never in a controller
- Parameter type must stay as `Model $model` — narrowing to a concrete class is a fatal PHP variance error

### 8. BaseObserver → `rules/base-observer.md`

- All lifecycle methods are no-op by default — override only what you need
- Return `false` from a before-event (`creating`, `updating`, `deleting`) to cancel the operation
- Register via `Model::observe()` in a ServiceProvider's `boot()`
- Parameter type must stay as `Model $model` — narrowing to a concrete class is a fatal PHP variance error

### 9. BaseJob → `rules/base-job.md`

- Override `shouldNotify()` → `true` to enable failure notifications
- Call `notifyStarted()` / `notifyCompleted()` manually inside `handle()` — they are opt-in
- Never override `failed()` — it handles rollback, logging, and notification automatically
- Override `errorContext()` to add domain-specific fields to the failure log

### 10. BaseApiException → `rules/base-exception.md`

- Three layers: ExceptionRenderer (global) → BaseApiException (domain) → handleException() (last resort)
- Implement `ShouldntReport` for expected client-fixable errors — keeps logs clean
- UUID (`exception_id`) appears in responses only from Layer 3 — never on domain exceptions
- Override `context()` to add domain-specific searchable fields to log entries

### 11. BaseExtensionServiceProvider → `rules/base-extension-service-provider.md`

- Module B never edits Module A's source files — all cross-module extension through ServiceProvider hooks
- Use the structured hooks: `extendModels()`, `extendResources()`, `extendServices()`, `extendRepositories()`, `extendOperators()`
- Always call `parent::boot()` first when overriding `boot()`
- `static::class` in all static registries — never `self::class`

### 12. BaseTestCase → `rules/base-test-case.md`

- Use envelope assertion helpers — never raw `assertStatus()` + `assertJson()`
- Payload key is `payload`, not `data`
- Always `array_merge(parent::defaultHeaders(), [...])` when overriding `defaultHeaders()`
- `RefreshDatabase` for tests spawning observers/jobs; `DatabaseTransactions` for most HTTP feature tests

### 13. BaseModel → `rules/base-model.md`

- Module B adds fillable/casts/relations/scopes/searchable from its own ServiceProvider — never edit the model source
- `static::class` in all static registries — never `self::class`
- Never register a model as `singleton()` or `scoped()` — models are stateful, always transient
- Extending `BaseModel` is recommended but not required — implement `HasSearchableColumns`/`HasRelationRegistry` to opt in without it
- For JSON columns, cast with `CoreFoundation\Casts\FluentJsonCast` for recursive property access instead of the built-in `array`/`object` casts

### 14. Coding Standards (cross-cutting) → `rules/coding-standards.md`

- No one-letter variable names anywhere — `$e` is always `$exception` (or a type-specific name for a narrowed catch)
- `Throwable`, never `Exception`, at safety-net layers (`handleException()`, `ExceptionRenderer`) — narrowing drops PHP `Error`s out of the response envelope
- Every repository is generated/written as interface + concrete pair — never the concrete class alone
- No direct model queries (`Model::query()`, `Model::where()`, ...) outside a repository's own methods
- Every user-facing message is a translation key — never a hardcoded string

## How to Apply

Always read the relevant rule file(s) before writing or reviewing code.

1. Identify which base class(es) are involved
2. Read the corresponding rule file from `rules/` — for anything touching a repository, a controller catch block, a variable name, or a user-facing message, also read `rules/coding-standards.md`
3. Check sibling files for existing patterns — follow those first per Consistency First
4. Apply the rules from the most specific file
