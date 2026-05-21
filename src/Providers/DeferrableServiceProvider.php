<?php

namespace CoreFoundation\Providers;

use Illuminate\Contracts\Support\DeferrableProvider;
use Illuminate\Support\ServiceProvider;

/**
 * DeferrableServiceProvider
 *
 * Base class for service providers that should only be loaded when their
 * services are actually needed — not on every request.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHAT DEFERRABLE MEANS                                                       │
 * │                                                                             │
 * │ A deferrable provider is NOT loaded on every request. Laravel reads its    │
 * │ provides() method at cache-build time and only boots the provider when      │
 * │ one of those service keys is actually resolved from the container.          │
 * │                                                                             │
 * │ This is different from defer() the helper:                                  │
 * │   - DeferrableProvider: deferred LOADING of a ServiceProvider               │
 * │   - defer() helper:     deferred EXECUTION of a callback post-response     │
 * │                                                                             │
 * │ They share the word "defer" but are completely unrelated concepts.          │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHEN TO USE                                                                 │
 * │                                                                             │
 * │ Use DeferrableServiceProvider when your provider registers services that:   │
 * │   - Are only needed in specific routes (e.g. payment processing)           │
 * │   - Are heavy to instantiate (e.g. third-party SDK clients)                │
 * │   - Are never needed on most requests (e.g. PDF generation, reporting)     │
 * │                                                                             │
 * │ DO NOT defer providers that register:                                       │
 * │   - Middleware (needed on every request)                                    │
 * │   - Event listeners (needed on every request)                              │
 * │   - Route definitions                                                       │
 * │   - Config merging                                                          │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE                                                                       │
 * │                                                                             │
 * │   class PdfServiceProvider extends DeferrableServiceProvider               │
 * │   {                                                                         │
 * │       public function register(): void                                      │
 * │       {                                                                     │
 * │           $this->app->singleton(PdfGenerator::class, fn() =>               │
 * │               new PdfGenerator(config('pdf'))                               │
 * │           );                                                                │
 * │       }                                                                     │
 * │                                                                             │
 * │       // Declare what this provider registers —                             │
 * │       // Laravel will only boot this provider when one of these is needed  │
 * │       public function provides(): array                                     │
 * │       {                                                                     │
 * │           return [PdfGenerator::class];                                     │
 * │       }                                                                     │
 * │   }                                                                         │
 * │                                                                             │
 * │ IMPORTANT: Run php artisan optimize after adding a DeferrableProvider.     │
 * │ Laravel caches the provides() map — without it, deferred loading           │
 * │ may not work correctly.                                                     │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ OCTANE                                                                      │
 * │                                                                             │
 * │ DeferrableProvider works correctly with Octane. The provider is loaded     │
 * │ once on first resolution and stays in memory for subsequent requests.      │
 * │ The deferred loading decision is made once per worker boot, not per        │
 * │ request — which is actually a performance improvement over standard PHP.   │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
abstract class DeferrableServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Declare all service class names this provider registers.
     *
     * Laravel reads this at optimize time to build the deferred service map.
     * The provider is only booted when one of these keys is resolved.
     *
     * Must include EVERY binding registered in register() — missing entries
     * will cause those services to never be loaded.
     *
     * @return array<string>
     */
    abstract public function provides(): array;
}
