@php
/** @var \Laravel\Boost\Install\GuidelineAssist $assist */
@endphp
# CoreFoundation — Scaffold First, Always

Before writing any class, use the interactive scaffold. It generates the correct stubs, wires the namespace, and selects the right component set.

<code-snippet name="Scaffold a new CRUD module" lang="bash">
{{ $assist->artisanCommand('core:make Order') }}
</code-snippet>

Presets: **CRUD** (model through test, 13 files) or **Custom Feature** (service + DTO + test). List all available commands: `{{ $assist->artisanCommand('list core') }}`

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
