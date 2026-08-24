# Changelog

All notable changes to `core-foundation` will be documented in this file

## Unreleased

- **Breaking:** `BaseRepository::query()` is now `protected` (was `public`). Custom queries must live in named methods on the concrete repository — `QueryRepositoryContract` (which declared `query()` publicly) has been removed from `RepositoryContract`. This closes off the most common way repository cache invalidation was silently bypassed: an ad hoc `$repository->query()->...` call from a service or controller.
- Added `BaseRepository::updateQuietly()` — an explicit, opt-in "silent update" that skips cache invalidation and Eloquent model events. Not part of `WriteRepositoryContract`; must be added to a repository's own contract to be exposed.
- `php artisan core:make {Name}` now always generates a `{Name}RepositoryContract` interface alongside `{Name}Repository`, and wires the binding into the generated `ServiceProvider::registerBindings()`. Services now type-hint the contract, never the concrete repository class.
- `php artisan core:make {Name}` now also generates `lang/en/{name}.php` alongside the controller, and the generated controller resolves every response message through `$this->lang()` instead of relying on default `null` messages.
- Added `CoreFoundation\Casts\FluentJsonCast` — casts a JSON column to a recursive `Illuminate\Support\Fluent`, enabling property access on nested JSON (`$model->settings->theme->color`) instead of array subscripts.
- Renamed exception variables from `$e` to `$exception` (or a type-specific name) across the package's controllers, exception renderer, and documentation — `Throwable` is retained (not narrowed to `Exception`) at every safety-net layer so PHP `Error`s still get wrapped in the standard JSON envelope.
- Expanded the `core-foundation-best-practices` Boost skill: strengthened repository/controller rules and added a new cross-cutting `rules/coding-standards.md` (no direct model queries in business logic, translation-key-only messages, no one-letter variables, `Throwable` vs `Exception` at safety-net layers).

## 1.1.0 - 2026-05-30

- Added Pessimistic Locking to `BaseRepository` (`lockForUpdate()`, `sharedLock()`).
- Introduced Atomic Updates (Compare-and-Swap) to `BaseRepository` via `updateAtomic()`.
- Added `StaleDataException` for handling optimistic concurrency conflicts (HTTP 409).
- Improved `BaseRepository` read queries to automatically bypass cache when a lock is applied.
- Renamed `ApplicationState` to `ApplicationContext` to better reflect its architectural purpose.

## 1.0.0 - 2026-05-29

- Initial open source release of `core-foundation`.
- Features robust architecture base classes: `BaseModel`, `BaseController`, `BaseService`, `BaseRepository`, `BaseRequest`, `BaseDataObject`, `BaseResource`, `BaseCollection`.
- Includes Server-Timing profiling middleware (`ProfilingMiddleware`, `ServerTimingMiddleware`).
- Adds filtering, sorting, and caching for repositories via `FilterApplicator`, `SortApplicator`, and `RepositoryCache`.
- Provides pipeline execution hooks (`HasPipeline`) and namespace-based event dispatching (`HasEvent`).
- Introduces conditional service preference capabilities (`HasFactory`).
