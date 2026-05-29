<?php

namespace CoreFoundation\Transformers;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * BaseResource
 *
 * Abstract JSON API resource transformer. Enforces a consistent field-map contract
 * and gives modules a static registry to add, override, or remove fields without
 * touching the resource class itself.
 *
 * USAGE:
 *
 *   final class UserResource extends BaseResource
 *   {
 *       protected function fields(Request $request): array
 *       {
 *           return [
 *               'id'    => $this->id,
 *               'name'  => $this->name,
 *               'email' => $this->email,
 *           ];
 *       }
 *   }
 *
 * Modular extension from ServiceProvider::boot() — Module B never touches UserResource.php:
 *
 *   // Add a new field
 *   UserResource::addField('plan', fn ($user, $req) => $user->subscription?->plan_code);
 *
 *   // Override an existing field (same key — registry wins over base)
 *   UserResource::addField('email', fn ($user, $req) => $req->user()?->isAdmin()
 *       ? $user->email
 *       : str_repeat('*', 6) . '@hidden'
 *   );
 *
 *   // Remove a field entirely (compliance / PII stripping)
 *   UserResource::removeField('internal_notes');
 *
 * Opt-in timestamps inside fields():
 *
 *   return $this->withTimestamps([
 *       'id'   => $this->id,
 *       'name' => $this->name,
 *   ]);
 *
 * In a controller:
 *
 *   return $this->successResponse('User fetched.', new UserResource($userDataObject));
 */
abstract class BaseResource extends JsonResource
{
    // =========================================================================
    // Static registries — modular extensibility
    // =========================================================================

    /** @var array<class-string, array<string, Closure(mixed, Request): mixed>> */
    private static array $additionalFields = [];

    /** @var array<class-string, list<string>> */
    private static array $removedFields = [];

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
     * Not called automatically — opt in per resource:
     *
     *   return $this->withTimestamps([
     *       'id'   => $this->id,
     *       'name' => $this->name,
     *   ]);
     */
    protected function withTimestamps(array $fields): array
    {
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
