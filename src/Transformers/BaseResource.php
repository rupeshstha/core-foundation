<?php

namespace CoreFoundation\Transformers;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BaseResource
 *
 * Abstract JSON API resource transformer. Enforces a consistent field-map contract
 * and gives modules a static registry to add, override, or remove fields without
 * touching the resource class itself.
 *
 * $this->resource accepts any value — Eloquent model, BaseDataObject (DTO), or array.
 * The service layer is responsible for all computation. This class maps the result
 * to response fields only. Zero business logic here.
 *
 * TYPICAL USAGE — service returns a DTO, resource maps it:
 *
 *   // Service computes everything and returns a typed DTO
 *   public function summary(): OrderSummaryData
 *   {
 *       return new OrderSummaryData(
 *           totalOrders:       $orders->count(),
 *           totalRevenue:      $orders->sum('total'),
 *           averageOrderValue: $orders->avg('total'),
 *       );
 *   }
 *
 *   // Resource maps DTO fields — no computation
 *   final class OrderSummaryResource extends BaseResource
 *   {
 *       protected function fields(Request $request): array
 *       {
 *           return [
 *               'total_orders'        => $this->resource->totalOrders,
 *               'total_revenue'       => $this->resource->totalRevenue,
 *               'average_order_value' => $this->resource->averageOrderValue,
 *           ];
 *       }
 *   }
 *
 * SIMPLE MODEL USAGE:
 *
 *   final class UserResource extends BaseResource
 *   {
 *       protected function fields(Request $request): array
 *       {
 *           return $this->withTimestamps([
 *               'id'    => $this->resource->id,
 *               'name'  => $this->resource->name,
 *               'email' => $this->resource->email,
 *           ]);
 *       }
 *   }
 *
 * Modular extension from ServiceProvider::boot() — Module B never touches UserResource.php:
 *
 *   // Add a new field
 *   UserResource::addField('plan', fn ($resource, $req) => $resource->subscription?->plan_code);
 *
 *   // Override an existing field (same key — registry wins over base)
 *   UserResource::addField('email', fn ($resource, $req) => $req->user()?->isAdmin()
 *       ? $resource->email
 *       : str_repeat('*', 6) . '@hidden'
 *   );
 *
 *   // Remove a field entirely (compliance / PII stripping)
 *   UserResource::removeField('internal_notes');
 *
 * In a controller:
 *
 *   return $this->successResponse('Summary fetched.', new OrderSummaryResource($summaryData));
 */
abstract class BaseResource extends JsonResource
{
    // =========================================================================
    // Static registries — modular extensibility
    // =========================================================================

    /** @var array<class-string, array<string, Closure(mixed, Request): mixed>> */
    protected static array $additionalFields = [];

    /** @var array<class-string, list<string>> */
    protected static array $removedFields = [];

    /**
     * Disable Laravel's default "data" wrapping.
     * BaseController::successResponse() owns the response envelope.
     */
    public static $wrap = null;

    /**
     * Add or override a response field from a ServiceProvider.
     *
     * The resolver receives the raw resource and the current Request.
     * If the key already exists in fields(), the registered value replaces it.
     *
     *   UserResource::addField('plan', fn ($user, $req) => $user->subscription?->plan_code);
     */
    final public static function addField(string $key, Closure $resolver): void
    {
        static::$additionalFields[static::class][$key] = $resolver;
    }

    /**
     * Exclude a field from the response, even if declared in fields() or added via addField().
     *
     * Removals are applied last — they always win over additions.
     *
     *   UserResource::removeField('internal_notes');
     */
    final public static function removeField(string $key): void
    {
        static::$removedFields[static::class][] = $key;
    }

    // =========================================================================
    // Opt-in helpers — call inside fields(), not automatic
    // =========================================================================

    /**
     * Merge ISO 8601 timestamps into the given field map.
     *
     * Model-only helper — silently skips if $this->resource is not an Eloquent model.
     * DTOs that carry timestamps should include them explicitly in fields().
     *
     *   return $this->withTimestamps([
     *       'id'   => $this->resource->id,
     *       'name' => $this->resource->name,
     *   ]);
     */
    protected function withTimestamps(array $fields): array
    {
        if (! $this->resource instanceof Model) {
            return $fields;
        }

        return array_merge($fields, [
            'created_at' => $this->resource->created_at?->toISOString(),
            'updated_at' => $this->resource->updated_at?->toISOString(),
        ]);
    }

    // =========================================================================
    // Contract — child must declare base field map
    // =========================================================================

    /**
     * Return the base field map for this resource.
     *
     * Override this — not toArray() — to define the resource shape.
     * Laravel's $this->when(), $this->whenLoaded(), $this->mergeWhen() all work here.
     *
     *   protected function fields(Request $request): array
     *   {
     *       return [
     *           'id'      => $this->id,
     *           'name'    => $this->name,
     *           'address' => new AddressResource($this->whenLoaded('address')),
     *       ];
     *   }
     */
    abstract protected function fields(Request $request): array;

    // =========================================================================
    // Final — enforces pipeline: base → additions/overrides → removals
    // =========================================================================

    /**
     * Build the final field map.
     *
     * Pipeline: fields() → addField() overrides → removeField() exclusions.
     * Marked final — the pipeline must not be bypassed. Override fields() instead.
     */
    final public function toArray(Request $request): array
    {
        $fields = $this->fields($request);

        foreach (static::$additionalFields[static::class] ?? [] as $key => $resolver) {
            $fields[$key] = $resolver($this->resource, $request);
        }

        foreach (static::$removedFields[static::class] ?? [] as $key) {
            unset($fields[$key]);
        }

        return $fields;
    }
}
