# Streamlined Foundations for Robust API

[![Latest Version on Packagist](https://img.shields.io/packagist/v/rupeshstha/core-foundation.svg?style=flat-square)](https://packagist.org/packages/rupeshstha/core-foundation)
[![Total Downloads](https://img.shields.io/packagist/dt/rupeshstha/core-foundation.svg?style=flat-square)](https://packagist.org/packages/rupeshstha/core-foundation)

[![https://core-foundation-doc.rupeshstha.com.np/?ref=github](https://github.com/rupeshstha/core-foundation/blob/main/artifacts/Images/core-foundation-cover.jpg?raw=true)](https://core-foundation-doc.rupeshstha.com.np/?ref=github)

[![Documentation]()](https://core-foundation-doc.rupeshstha.com.np/)

`core-foundation` is an enterprise-grade Laravel package designed to accelerate the development of robust, scalable, and modular APIs. It replaces hand-rolled base classes with highly opinionated, extensible, and Octane-safe abstractions. 

This foundation is built for developers creating modular monoliths who require strong conventions, structured extensibility, and built-in observability out of the box.

## Key Features

- **Modular Extensibility:** Extend `BaseModel`, `BaseResource`, `BaseService`, and Repositories across module boundaries without modifying the source module. Uses structured Service Provider hooks (`extendModels`, `extendResources`, etc.).
- **Consistent Response Envelopes:** Enforces a uniform API response shape (`message`, `payload`, `meta`, `errors`, `exception_id`) across all successful and failed endpoints using `BaseController`.
- **Three-Layer Exception Handling:** Features a predictable error system with an `ExceptionRenderer` (handles framework exceptions), `BaseApiException` (domain exceptions), and `handleException()` (fatal controller safety net with UUIDs).
- **Built-in Server-Timing Profiling:** Gain real-time performance insights directly in browser DevTools. Auto-measure pipelines, cache hit/miss rates, and query times via `ServerTimingMiddleware`.
- **Octane Safety:** Ensures complete safety in long-running processes (Laravel Octane). Contexts are scoped and reset per request automatically.
- **Race Condition Handling:** Native support for Pessimistic Locking (`lockForUpdate`, `sharedLock`) and Atomic Updates (Compare-and-Swap) via `updateAtomic` in repositories to prevent lost updates in high-concurrency scenarios.
- **Repository Pattern & Caching:** Advanced `BaseRepository` with `FilterApplicator` and `SortApplicator`. Built-in tag-based read-through caching that automatically invalidates upon create, update, and delete actions.
- **DTOs and Pipelines:** Standardized `BaseDataObject` (DTOs) and `HasPipeline` / `HasEvent` traits to enforce clean data transitions and logic decoupling.
- **Built-in AI Assistant Context (Agentic Skills):** This package ships with expert-level `.md` rule files and guidelines (located in `resources/boost/skills/core-foundation-best-practices`). When used with an AI coding assistant, it instantly provides the AI with the exact architecture rules, patterns, and conventions of CoreFoundation, ensuring your AI writes compliant code from day one.

## Installation

You can install the package via composer:

```bash
composer require rupeshstha/core-foundation
```

Once installed, you can publish the package configuration:

```bash
php artisan vendor:publish --tag=core_foundation
php artisan vendor:publish --tag=core-foundation-server-timing
```

## Quick Start

CoreFoundation provides interactive scaffolding to rapidly generate compliant modules.

```bash
php artisan core:make Order
```
This generates your Model, Factory, Repository, Service, Controller, Requests, Resource, DTO, Policy, Observer, Provider, and Feature Test – properly wired and ready to use.

## Usage

For detailed usage instructions, guidelines on writing base classes, and architectural documentation, please refer to the official documentation.

[Read the Full Documentation](https://core-foundation-doc.rupeshstha.com.np/)

### Testing

```bash
composer test
```

### Code Quality Analysis
Analyze your project's PHP method complexity and code smell scores using the built-in analyzer:
```bash
php artisan core:analyse
```

### Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

### Security

If you discover any security related issues, please email contact@rupeshstha.com.np instead of using the issue tracker.

## Credits

-   [Rupesh Shrestha](https://github.com/rupeshstha)
-   [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
