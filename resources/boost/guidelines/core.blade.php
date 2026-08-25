@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
# CoreFoundation — Scaffold First, Always

Before writing any class, use the interactive scaffold. It generates the correct stubs, wires the namespace, and selects the right component set.

<code-snippet name="Scaffold a new CRUD module" lang="bash">
{{ $assist->artisanCommand('core:make Order') }}
</code-snippet>

Presets: **CRUD** (model through test, 13 files) or **Custom Feature** (service + DTO + test). List all available commands: `{{ $assist->artisanCommand('list core') }}`

## Repositories — Interface + Concrete, Always

Selecting `repository` in the scaffold generates **both** `{Name}RepositoryContract` and `{Name}Repository` — never write a repository without its contract. Services and controllers inject the contract, never the concrete class. `query()` is `protected` — it only exists to be called from named methods *inside* the concrete repository; a service or controller can never reach it directly, and never should. Full rules: `resources/boost/skills/core-foundation-best-practices/rules/base-repository.md`.

## Every Message Is a Translation Key

No hardcoded strings in a response, log line, or exception message. Controllers: `$this->lang('key')` (auto-generates `lang/en/{name}.php`). Anywhere else: `CoreFoundation\Support\Lang::get('domain::key')`.

## Response Helpers

Use these on every `BaseController` — never `response()->json()`:

<code-snippet name="Controller response helpers" lang="php">
return $this->successResponse(payload: new OrderResource($order));    // 200
return $this->createdResponse(payload: new OrderResource($order));    // 201
return $this->noContentResponse();                                    // 204
return $this->paginatedResponse($paginator);                          // 200 + meta.pagination
</code-snippet>

## Response Envelope — Never Deviate

<code-snippet name="Success envelope" lang="json">
{ "message": "...", "payload": { "id": 1 }, "meta": { "pagination": { "total": 100, "per_page": 15, "current_page": 1 } } }
</code-snippet>

<code-snippet name="Error envelope" lang="json">
{ "message": "...", "errors": { "field": ["message"] }, "exception_id": "uuid-on-fatal-500-only" }
</code-snippet>
