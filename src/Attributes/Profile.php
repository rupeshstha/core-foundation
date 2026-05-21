<?php

namespace CoreFoundation\Attributes;

use Attribute;

/**
 * #[Profile]
 *
 * Marks a service or repository method for automatic runtime profiling.
 * When ProfilingMiddleware is active and this layer is enabled in config,
 * the method's execution time is captured in the Server-Timing header.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE                                                                       │
 * │                                                                             │
 * │   class OrderService extends BaseService                                    │
 * │   {                                                                         │
 * │       #[Profile]                                                            │
 * │       public function place(array $validated): PlaceOrderData               │
 * │       {                                                                     │
 * │           // This method's duration appears in Server-Timing as:            │
 * │           // "OrderService.place;desc="OrderService::place";dur=X"          │
 * │       }                                                                     │
 * │                                                                             │
 * │       #[Profile(label: 'fetch-orders', description: 'Fetch paginated orders')]
 * │       public function fetchAll(array $filters = []): mixed                  │
 * │       {                                                                     │
 * │           // Custom label and description in Server-Timing header           │
 * │       }                                                                     │
 * │   }                                                                         │
 * │                                                                             │
 * │ NOTE: This attribute does nothing on its own. It is a metadata marker.     │
 * │ The actual measurement is applied by HasProfilable when the class uses      │
 * │ the trait, or by ProfilingMiddleware via reflection for classes that        │
 * │ don't use the trait but are registered in config/profiling.php.            │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHEN TO PROFILE                                                             │
 * │                                                                             │
 * │ Profile methods that:                                                       │
 * │   - Have known or suspected performance issues                              │
 * │   - Are called frequently and whose cost compounds                          │
 * │   - Cross architectural boundaries (service → repository → DB)             │
 * │   - Call external services (APIs, queues, cache)                            │
 * │                                                                             │
 * │ Do NOT profile methods that:                                                │
 * │   - Are trivial (getters, setters, simple transforms)                      │
 * │   - Are already measured by a parent (don't double-measure a pipeline)     │
 * │   - Run on every request in production (adds overhead per call)            │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Profile
{
    public function __construct(
        /**
         * Custom Server-Timing metric label.
         * Defaults to: "{ClassName}.{methodName}" if null.
         */
        public readonly ?string $label = null,

        /**
         * Human-readable description shown in DevTools Server-Timing panel.
         * Defaults to: "{FullClassName}::{methodName}" if null.
         */
        public readonly ?string $description = null,

        /**
         * Whether to profile this method even in production.
         * Defaults to false — profiling is development-only by default.
         * Set to true only for methods where production profiling is explicitly needed.
         */
        public readonly bool $inProduction = false,
    ) {}
}
