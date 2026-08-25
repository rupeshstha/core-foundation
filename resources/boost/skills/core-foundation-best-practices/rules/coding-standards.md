# Coding Standards — Applies Everywhere in a CoreFoundation Project

These are cross-cutting rules, not tied to one base class. They apply to every file generated or edited, including ones the other rule files don't specifically cover.

## No One-Letter (or Otherwise Meaningless) Variable Names

`$e`, `$m`, `$p`, `$q`, `$s` — none of these are acceptable, in a `catch` block, a closure parameter, or anywhere else. Name every variable for what it holds.

Incorrect:
```php
} catch (Throwable $e) {
    return $this->handleException($e);
}

$results = array_filter($orders, fn ($o) => $o->status === 'pending');
```

Correct:
```php
} catch (Throwable $exception) {
    return $this->handleException($exception);
}

$results = array_filter($orders, fn (Order $order) => $order->status === 'pending');
```

This applies to `catch` blocks specifically: default to `$exception`. When the catch is narrowed to a specific type, name the variable after that type — `QueryException $queryException`, `ValidationException $validationException` — never abbreviate it back down to a single letter.

## `Throwable` at Safety-Net Layers, Not `Exception`

`HasExceptionHandler::handleException()`, `ExceptionRenderer`, and any other "last resort" catch exist specifically to guarantee the standard JSON error envelope for *everything* — including PHP `Error`s (`TypeError`, `ArgumentCountError`, `DivisionByZeroError`), which are not `Exception` subclasses and are not caught by `catch (Exception $exception)`. Keep those layers catching `Throwable`.

Narrower application code may legitimately catch a specific `Exception` subclass when it's handling one known failure mode inline — that's a different situation from the safety net and is fine.

## Every Repository Follows the Interface + Bind Pattern — No Exceptions

Covered in full in `rules/base-repository.md`. The short version: a repository is never generated or hand-written without its `{Name}RepositoryContract`, and it is never injected anywhere by concrete class. If you're about to write `class SomethingRepository extends BaseRepository` without an accompanying contract interface, stop and add one first.

## No Direct Model Queries in Business Logic

`Model::query()`, `Model::where()`, `Model::find()` etc. do not appear in a controller, service, job, listener, or event — only inside a repository's own methods (via the protected `$this->query()`), or in migrations/seeders/factories where no repository layer exists. See `rules/base-repository.md` for why (cache invalidation, tenant scoping, the `searchable()` guard all live in the repository).

## Every User-Facing Message Is a Translation Key

No hardcoded English string reaches a response, a log line meant for a human, or an exception message. Controllers use `$this->lang('key')` (see `rules/base-controller.md`); anything else uses `CoreFoundation\Support\Lang::get('domain::key')`. This is not optional polish — it's what makes the app localizable without a retrofit, and it's enforced the same way for a one-off error message as for a whole page of copy.

## Silent Repository Writes Are Rare and Explicit

`BaseRepository::updateQuietly()` exists for the narrow case of updating a column that no cache and no observer cares about. It is not part of the default `WriteRepositoryContract` — a repository's contract must explicitly declare it before a service can call it. Reaching for it as a shortcut to "make an update faster" without checking both conditions in `rules/base-repository.md` reintroduces stale-cache bugs. Default to `update()`.
