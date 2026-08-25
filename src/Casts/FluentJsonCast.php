<?php

namespace CoreFoundation\Casts;

use Illuminate\Support\Fluent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

/**
 * FluentJsonCast
 *
 * Casts a JSON column to a recursive Fluent object — property access on
 * nested JSON structures instead of plain array subscript access.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE                                                                       │
 * │                                                                             │
 * │   protected function casts(): array                                         │
 * │   {                                                                         │
 * │       return ['settings' => FluentJsonCast::class];                         │
 * │   }                                                                         │
 * │                                                                             │
 * │   // Column stores: {"theme":{"color":"dark","font":{"size":14}}}           │
 * │                                                                             │
 * │   $order->settings->theme->color;       // 'dark' — not ['theme']['color']  │
 * │   $order->settings->theme->font->size;  // 14 — nested objects too          │
 * │                                                                             │
 * │   // A list of objects stays a plain array; each object is still Fluent:    │
 * │   $order->settings->items[0]->sku;                                          │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WRITING                                                                     │
 * │                                                                             │
 * │ Assign a plain array or a Fluent instance — both serialize the same way:    │
 * │                                                                             │
 * │   $order->settings = ['theme' => ['color' => 'dark']];                      │
 * │   $order->settings = new Fluent(['theme' => ['color' => 'dark']]);          │
 * │                                                                             │
 * │ Do NOT assign an already-encoded JSON string — it will be encoded again     │
 * │ (double-encoded), the same way Eloquent's built-in 'array'/'object' casts   │
 * │ behave for the same mistake.                                                │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * @implements CastsAttributes<Fluent<string, mixed>|null, Fluent<string, mixed>|array<string, mixed>|null>
 */
class FluentJsonCast implements CastsAttributes
{
    /**
     * Decode the JSON column and recursively wrap every associative
     * structure (JSON "object") in a Fluent instance. Sequential arrays
     * (JSON "lists") stay plain PHP arrays — index access is already the
     * natural way to reach them — but their elements are wrapped too.
     *
     * @param  array<string, mixed>  $attributes
     * @return Fluent<string, mixed>|null
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Fluent
    {
        if ($value === null) {
            return null;
        }

        $decoded = json_decode((string) $value, true, 512, JSON_THROW_ON_ERROR);

        return $this->wrap($decoded);
    }

    /**
     * Serialize back to a JSON string. json_encode() recurses through plain
     * arrays and any nested Fluent instances on its own — Fluent implements
     * JsonSerializable — so no manual unwrapping is needed here.
     *
     * @param  Fluent<string, mixed>|array<string, mixed>|null  $value
     * @param  array<string, mixed>  $attributes
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function wrap(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (array_is_list($value)) {
            return array_map($this->wrap(...), $value);
        }

        return new Fluent(array_map($this->wrap(...), $value));
    }
}
