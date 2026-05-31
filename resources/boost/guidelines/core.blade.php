@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
## CoreFoundation — Enterprise Laravel API Starter Kit

CoreFoundation is a foundational package for building modular monolith APIs on Laravel 11+ / PHP 8.3+.
It provides abstract base classes, traits, conventions, and developer tooling that enforce scalable
architecture. When in doubt, check sibling files for the correct pattern — conventions are consistent
across all layers.

---

### Scaffolding — Always Start Here

Before writing any class manually, use the interactive scaffold command. It generates the correct stubs,
wires namespace and paths, and pre-selects the right component set for the scenario.

<code-snippet name="Scaffold a new CRUD module" lang="bash">
{{ $assist->artisanCommand('core:make Order') }}
</code-snippet>

Presets:
- **CRUD** — model, factory, repository, service, controller, store-request, update-request, resource,
  collection, data-object, policy, provider, test
- **Custom Feature** — service, data-object, test only

To list all available commands: {{ $assist->artisanCommand('list core') }}

---

### Response Envelope — Never Deviate

Every API response produced by this codebase follows one of these two shapes. Do not introduce
new shapes, wrappers, or additional top-level keys.

<code-snippet name="Success response envelope" lang="json">
{
    "message": "Order retrieved.",
    "payload": { "id": 1, "status": "pending" },
    "meta": { "pagination": { "total": 100, "per_page": 15, "current_page": 1 } }
}
</code-snippet>

<code-snippet name="Error response envelope" lang="json">
{
    "message": "Validation failed.",
    "errors": { "total": ["The total field is required."] },
    "exception_id": "uuid-only-on-fatal-500-errors"
}
</code-snippet>

Envelope helpers on `BaseController` — use these, never `response()->json()` directly:

<code-snippet name="BaseController response helpers" lang="php">
return $this->success(new OrderResource($order), 'Order retrieved.');  // 200
return $this->created(new OrderResource($order));                       // 201
return $this->noContent();                                              // 204
</code-snippet>

---

### Exception System — Three Layers

The exception system has three layers. Each has a specific responsibility. Never collapse them.

```
Layer 1 — ExceptionRenderer      Registered in ServiceProvider. Handles all framework exceptions
                                  (ValidationException, AuthenticationException, etc.) globally.
                                  No try/catch needed in controllers for these.

Layer 2 — BaseApiException        Domain exceptions. Self-render via render(). Silent exceptions
                                  implement ShouldntReport — no UUID, no log entry.

Layer 3 — handleException()       Last-resort controller safety net. Always generates UUID, always
                                  logs with context, always returns 500.
```

<code-snippet name="Silent domain exception (Layer 2)" lang="php">
use Illuminate\Contracts\Debug\ShouldntReport;
use CoreFoundation\Exceptions\BaseApiException;

class OrderNotFoundException extends BaseApiException implements ShouldntReport
{
    public function __construct(private readonly int $id)
    {
        parent::__construct("Order {$id} not found.");
    }

    public function render($request): \Illuminate\Http\JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'errors'  => [],
        ], 404);
    }
}
</code-snippet>

<code-snippet name="Fatal exception (Layer 3 fallback — controller only)" lang="php">
public function show(int $id): JsonResponse
{
    try {
        return $this->success(new OrderResource($this->repository->fetchById($id)));
    } catch (\Throwable $e) {
        return $this->handleException($e); // generates UUID, logs, returns 500
    }
}
</code-snippet>

Rules:
- **Never** add an exception class map in a controller (`catch (SpecificException)` blocks)
- **Never** catch `ValidationException` manually — Layer 1 handles it
- `exception_id` (UUID) appears **only** in Layer 3 fatal responses — never in domain exceptions
- Override `context()` on `BaseApiException` subclasses to add searchable log fields

---

### Service Layer

Services hold business logic. Input is always `array $validated` or a typed `BaseDataObject`. Output is
always a `BaseDataObject` subclass — never `JsonResponse`, never a raw Eloquent model, never a plain array.

<code-snippet name="Service method with pipeline and event" lang="php">
use CoreFoundation\Services\BaseService;
use CoreFoundation\Traits\HasEvent;
use CoreFoundation\Traits\HasPipeline;

class OrderService extends BaseService
{
    use HasPipeline, HasEvent;

    public function place(array $validated): PlaceOrderData
    {
        return $this->throughPipes('place', $validated, function (array $data): PlaceOrderData {
            $order = $this->repository->create($data);

            $this->dispatch('order.placed', $order);

            return PlaceOrderData::fromArray($order->toArray());
        });
    }
}
</code-snippet>

`HasEvent` vs `HasPipeline` — they are not interchangeable:

| Trait | Purpose | Can modify data? |
|---|---|---|
| `HasEvent` | Fire-and-forget pub/sub (audit, notifications, side-effects) | No |
| `HasPipeline` | Execution hooks — before/after core logic | Yes |

Register pipes from a ServiceProvider, never inside the service itself:

<code-snippet name="Registering a pipe from ServiceProvider" lang="php">
// In OrderServiceProvider::extendServices():
OrderService::addPipe('place', ValidateInventoryPipe::class);
OrderService::addPipe('place', ApplyDiscountPipe::class);
</code-snippet>

---

### Repository Layer

<code-snippet name="Concrete repository" lang="php">
use CoreFoundation\Repositories\BaseRepository;
use CoreFoundation\Repositories\Contracts\RepositoryContract;

class OrderRepository extends BaseRepository implements RepositoryContract
{
    protected function setModel(): string
    {
        return Order::class;
    }

    // Whitelist of filterable/sortable columns — SQL injection guard.
    protected function searchable(): array
    {
        return ['user_id', 'status', 'total', 'created_at'];
    }

    // Custom queries ALWAYS start from $this->query() — never Model::query() directly.
    public function pendingOlderThan(int $days): \Illuminate\Database\Eloquent\Collection
    {
        return $this->query()
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subDays($days))
            ->get();
    }
}
</code-snippet>

Contract selection:

| Scenario | Implement |
|---|---|
| Full CRUD + custom queries | `RepositoryContract` (extends all three) |
| Read-only (reports, analytics) | `ReadRepositoryContract` only |
| Write-only (event sourcing) | `WriteRepositoryContract` only |
| Custom queries only | `QueryRepositoryContract` only |

Repository binding and cache rules:
- Always bind as `$this->app->bind()` (transient) — **never** `singleton()` or `scoped()`
- `fetchAll` and `fetchById` are cached by default — override `cachedMethods()` to change
- create/delete = `flushModel()` (full tag flush); update = `flushRecord()` (granular)
- Register `RepositoryCacheObserver` in `boot()` to wire automatic cache busting

---

### Filter Operators

The filter system uses config-driven operators. Each operator is a `final` class implementing `FilterOperator`.
Built-in identifiers: `__eq_`, `__neq_`, `__gt_`, `__gte_`, `__lt_`, `__lte_`, `__like_`, `__nlike_`,
`__null_`, `__nnull_`, `__in_`, `__nin_`, `__or_`, `__and_`. (`__andor_` is opt-in, not in default config.)

<code-snippet name="Custom filter operator" lang="php">
use CoreFoundation\Repositories\Filter\Contracts\FilterOperator;
use Illuminate\Database\Eloquent\Builder;

final class BetweenOperator implements FilterOperator
{
    public function identifier(): string { return '__between_'; }

    public function apply(Builder $query, string $column, mixed $value): Builder
    {
        [$min, $max] = explode(',', $value, 2);
        return $query->whereBetween($column, [$min, $max]);
    }
}
</code-snippet>

<code-snippet name="Registering a custom operator" lang="php">
// In ServiceProvider::boot():
use CoreFoundation\Repositories\Filter\FilterApplicator;

FilterApplicator::addOperator(new BetweenOperator);
</code-snippet>

---

### Model Extensibility — Static Registration from ServiceProvider

Module B must **never** edit Module A's model source. All extensions are registered from a ServiceProvider.

<code-snippet name="Extending a model from another module's ServiceProvider" lang="php">
// In SubscriptionServiceProvider::boot() — Order model is untouched:
use App\Models\Order;

Order::addFillable(['subscription_id', 'plan_code']);
Order::addCast(['subscription_id' => 'integer', 'renews_at' => 'datetime']);
Order::addRelation('subscription', fn (Order $o) => $o->hasOne(Subscription::class));
Order::addScope(new ActiveSubscriptionScope, 'active_subscription');
Order::addSearchable(['subscription_id', 'plan_code']);
</code-snippet>

All static registries key by `static::class` — **never** `self::class`. This prevents subclass collision
in modular extension scenarios.

---

### BaseExtensionServiceProvider — Structured Module Hooks

Every module's ServiceProvider extends `BaseExtensionServiceProvider`. The five structured hooks enforce
a predictable, auditable extension surface.

<code-snippet name="Module ServiceProvider" lang="php">
use CoreFoundation\Providers\BaseExtensionServiceProvider;
use App\Models\Order;
use App\Policies\OrderPolicy;
use App\Observers\OrderObserver;
use Illuminate\Support\Facades\Gate;

class OrderServiceProvider extends BaseExtensionServiceProvider
{
    protected function registerBindings(): void
    {
        $this->app->bind(OrderRepository::class);
    }

    protected function extendModels(): void
    {
        $this->model(Order::class)
            ->fillable(['subscription_id'])
            ->relation('subscription', fn ($o) => $o->hasOne(Subscription::class));
    }

    protected function extendResources(): void
    {
        $this->resource(OrderResource::class)
            ->field('subscription', fn ($order, $req) => $order->subscription?->name)
            ->remove('internal_notes');
    }

    protected function extendServices(): void
    {
        $this->service(OrderService::class)
            ->pipe('place', ValidateInventoryPipe::class);
    }

    public function boot(): void
    {
        parent::boot();

        Gate::policy(Order::class, OrderPolicy::class);
        Order::observe(OrderObserver::class);
    }
}
</code-snippet>

---

### BaseResource — Modular Field Registry

<code-snippet name="Concrete resource" lang="php">
use CoreFoundation\Transformers\BaseResource;
use Illuminate\Http\Request;

class OrderResource extends BaseResource
{
    protected function fields(Request $request): array
    {
        return [
            'id'         => $this->resource->id,
            'status'     => $this->resource->status,
            'total'      => $this->resource->total,
        ];
    }
}
</code-snippet>

Field pipeline: `fields()` → modular `addField()` additions → `removeField()` exclusions.
Use `$this->withTimestamps($fields)` to append `created_at` / `updated_at` in ISO 8601.
`$wrap` is `null` — the envelope wrapping is owned by the controller, not the resource.

### BaseCollection — Always Declare \$collects

<code-snippet name="Resource collection" lang="php">
use CoreFoundation\Transformers\BaseCollection;

class OrderCollection extends BaseCollection
{
    public string $collects = OrderResource::class; // always explicit — no naming-convention magic
}
</code-snippet>

---

### Data Objects (DTOs)

- Namespace is `CoreFoundation\Manipulators\BaseDataObject` (not `CoreFoundation\DataObjects`)
- Services return DTOs — never raw Eloquent models, never plain arrays
- Pattern A: generic bag via `new static($data)` / `fromArray()`
- Pattern B: typed `readonly` properties for domain-critical DTOs

<code-snippet name="Typed DTO (Pattern B)" lang="php">
use CoreFoundation\Manipulators\BaseDataObject;

class PlaceOrderData extends BaseDataObject
{
    public function __construct(
        public readonly int    $orderId,
        public readonly string $status,
        public readonly float  $total,
    ) {
        parent::__construct([
            'order_id' => $orderId,
            'status'   => $status,
            'total'    => $total,
        ]);
    }

    public static function fromArray(array $data): static
    {
        return new static(
            orderId: $data['id'],
            status:  $data['status'],
            total:   (float) $data['total'],
        );
    }
}
</code-snippet>

---

### Form Requests — Never Override rules()

`rules()` is `final`. It assembles `baseRules()` + `storeRules()` + `updateRules()` automatically.
Always override the scoped method, never `rules()` itself.

<code-snippet name="Store and update requests" lang="php">
use CoreFoundation\Http\Requests\BaseRequest;

class StoreOrderRequest extends BaseRequest
{
    public function authorize(): bool { return true; }

    protected function storeRules(): array
    {
        return [
            'total'    => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
        ];
    }
}

class UpdateOrderRequest extends BaseRequest
{
    public function authorize(): bool { return true; }

    protected function updateRules(): array
    {
        return [
            'status' => ['sometimes', 'string', 'in:pending,confirmed,cancelled'],
        ];
    }
}
</code-snippet>

---

### Policies — Deny by Default

All abilities on `BasePolicy` return `Response::deny()`. Override only what you intend to grant.
Not overriding an ability = access denied — this is intentional for enterprise security baseline.

<code-snippet name="Policy with selective grants" lang="php">
use CoreFoundation\Policies\BasePolicy;
use Illuminate\Auth\Access\Response;

class OrderPolicy extends BasePolicy
{
    public function viewAny(mixed $user): Response|bool
    {
        return $user->hasPermission('orders.list');
    }

    public function view(mixed $user, mixed $model): Response|bool
    {
        return $user->id === $model->user_id
            ? Response::allow()
            : Response::deny('You do not own this order.');
    }

    // create(), update(), delete() not overridden = denied for everyone
}
</code-snippet>

---

### Observers — No-op Defaults

Override only the lifecycle events you need. Methods you do not override are no-ops — safe to omit.

<code-snippet name="Observer with typed PHPDoc" lang="php">
use CoreFoundation\Observers\BaseObserver;

/** @@extends BaseObserver<\App\Models\Order> */
class OrderObserver extends BaseObserver
{
    public function created(mixed $model): void
    {
        // $model is Order — PHPDoc resolves this in IDE
    }

    public function deleting(mixed $model): bool
    {
        return $model->canBeDeleted(); // return false to cancel
    }
}
</code-snippet>

---

### Jobs

<code-snippet name="Notifiable job" lang="php">
use CoreFoundation\Jobs\BaseJob;

class ProcessPaymentJob extends BaseJob
{
    public function __construct(private readonly int $orderId) {}

    protected function shouldNotify(): bool { return true; }

    protected function notificationPayload(): array
    {
        return ['order_id' => $this->orderId, 'stage' => 'payment'];
    }

    public function handle(): void
    {
        // logic
    }
}
</code-snippet>

---

### Testing — BaseTestCase + AssertsApiResponse

Tests extend `BaseTestCase` which disables rate-limiting and sets `Accept: application/json` on all requests.
Use the envelope-aware assertion helpers instead of raw `assertStatus()`.

<code-snippet name="Feature test with envelope assertions" lang="php">
it('returns the order', function () {
    $order = Order::factory()->create();

    $this->assertSuccessResponse(
        $this->getJson("/api/orders/{$order->id}")
    )->assertJsonPath('payload.id', $order->id);
});

it('rejects missing fields', function () {
    $this->assertValidationError(
        $this->postJson('/api/orders', []),
        'total', 'currency',
    );
});

it('paginates the listing', function () {
    Order::factory(30)->create();

    $this->assertPaginatedResponse(
        $this->getJson('/api/orders')
    );
});
</code-snippet>

Available assertion helpers: `assertSuccessResponse`, `assertCreatedResponse`, `assertNoContentResponse`,
`assertPaginatedResponse`, `assertValidationError`, `assertNotFoundResponse`, `assertUnauthorizedResponse`,
`assertForbiddenResponse`, `assertHasExceptionId`, `assertPayload`, `assertPayloadPath`,
`assertPayloadCount`, `assertPaginationTotal`.

All helpers return `TestResponse` for chaining with any native Laravel assertion.

---

### Visibility Rules — Apply These to Every Class

| What | Keyword |
|---|---|
| Guards, dispatchers, lifecycle triggers (things that must not be bypassed) | `final` |
| Internal implementation (no child access needed) | `private` |
| Extension points (what child classes override) | `protected` |
| All extension points | Must return a sensible no-op default (`[]`, `null`, `false`) |

---

### Octane Safety

| Class | Binding |
|---|---|
| `RepositoryCache` | `scoped()` — mutable per-request state |
| `ServerTimingService` | `scoped()` — resets per request |
| `FilterApplicator` | `singleton()` — stateless, static registry is intentionally persistent |
| `CacheKeyBuilder` | `singleton()` — pure functions only |
| `RelationTagResolver` | `singleton()` — static resolved cache persists intentionally |
| Concrete repositories | transient (`bind()`) — never registered |
| Eloquent models | **never** registered — always transient |

Register Octane reset listeners (`RequestReceived`, `TaskReceived`) for any state that must not bleed
between requests. Static registries (operators, relation tags) are intentionally NOT reset.

---

### Anti-Patterns — Never Do These

- `debug_backtrace()` for method detection — use `HasPipeline` explicit hooks instead
- `self::class` in static registries — always `static::class` (subclass collision)
- Exception class maps in controllers — put `render()` on the exception class
- `JsonResponse` returned from a service method
- Override `rules()` directly — use `baseRules()` / `storeRules()` / `updateRules()`
- `HasEvent` for execution hooks — that is `HasPipeline`'s job
- Models registered as singletons
- Calling the Context facade directly — use `ApplicationContext` subclasses
- Calling `Notification` facade directly from a service — use `HasNotification`
- `isProduction()` hardcoded in feature gates — use config-driven environment arrays
