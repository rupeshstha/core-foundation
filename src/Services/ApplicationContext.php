<?php

namespace CoreFoundation\Services;

use LogicException;
use Illuminate\Support\Facades\Context;

/**
 * ApplicationContext
 *
 * Abstract base class wrapping Laravel's Context facade.
 * Every domain creates one subclass — never use the Context facade directly.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHY THIS EXISTS                                                             │
 * │                                                                             │
 * │ Laravel's Context facade is a flat global key-value store. Without          │
 * │ discipline, multiple domains writing to it produce silent key collisions.   │
 * │ ApplicationContext enforces a mandatory namespace prefix per domain, making  │
 * │ all keys collision-safe and self-documenting.                               │
 * │                                                                             │
 * │ It also:                                                                    │
 * │  - Separates public context (logged) from hidden context (sensitive)        │
 * │  - Gives each domain a clean, typed API instead of raw string keys          │
 * │  - Keeps Context usage traceable — one class per domain, one place to look  │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ HOW TO CREATE A DOMAIN CONTEXT CLASS                                        │
 * │                                                                             │
 * │ 1. Extend ApplicationContext                                                │
 * │ 2. Declare a namespace prefix — must be unique across the application       │
 * │ 3. Add your own semantic set/get methods using the protected helpers        │
 * │ 4. Never call the Context facade directly — always go through this base     │
 * │                                                                             │
 * │   class OrderContext extends ApplicationContext                             │
 * │   {                                                                         │
 * │       // Step 2 — unique prefix, snake_case, domain-scoped                  │
 * │       protected function prefix(): string                                   │
 * │       {                                                                     │
 * │           return 'order';                                                   │
 * │       }                                                                     │
 * │                                                                             │
 * │       // Step 3 — semantic public state (appears in logs)                   │
 * │       public function setOrderId(int $id): static                           │
 * │       {                                                                     │
 * │           return $this->set('order_id', $id);                               │
 * │       }                                                                     │
 * │                                                                             │
 * │       public function orderId(): ?int                                       │
 * │       {                                                                     │
 * │           return $this->get('order_id');                                    │
 * │       }                                                                     │
 * │                                                                             │
 * │       // Hidden state — sensitive data, never written to logs               │
 * │       public function setPaymentToken(string $token): static                │
 * │       {                                                                     │
 * │           return $this->setHidden('payment_token', $token);                 │
 * │       }                                                                     │
 * │                                                                             │
 * │       public function paymentToken(): ?string                               │
 * │       {                                                                     │
 * │           return $this->getHidden('payment_token');                         │
 * │       }                                                                     │
 * │   }                                                                         │
 * │                                                                             │
 * │ Usage:                                                                      │
 * │                                                                             │
 * │   $context = new OrderContext;                                              │
 * │   $context->setOrderId(42)->setPaymentToken('tok_abc');                     │
 * │                                                                             │
 * │   // Or via the static factory:                                             │
 * │   OrderContext::make()->setOrderId(42);                                     │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ PREFIX RULES (enforced at runtime)                                          │
 * │                                                                             │
 * │  - Must be non-empty                                                        │
 * │  - snake_case only (a-z, 0-9, underscores)                                  │
 * │  - Must be unique per domain — two context classes must never share a prefix │
 * │                                                                             │
 * │ All context keys are stored as: {prefix}.{key}                              │
 * │ e.g. prefix = 'order', key = 'order_id' → stored as 'order.order_id'       │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ PUBLIC vs HIDDEN CONTEXT                                                    │
 * │                                                                             │
 * │ PUBLIC  → use set() / get()                                                 │
 * │           Written to logs automatically by Laravel.                         │
 * │           Use for: trace IDs, order IDs, job names, status flags            │
 * │                                                                             │
 * │ HIDDEN  → use setHidden() / getHidden()                                     │
 * │           Never written to logs. Survives the request→queue boundary.       │
 * │           Use for: tokens, passwords, PII, internal flags                   │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
/** @phpstan-consistent-constructor */
abstract class ApplicationContext
{
    // =========================================================================
    // Static factory
    // =========================================================================

    /**
     * No-arg constructor — enforces that all subclasses remain constructable
     * without arguments, making new static() safe from the make() factory.
     * Subclasses must not add required constructor parameters.
     */
    public function __construct() {}
    // =========================================================================
    // Contract — subclasses must declare this
    // =========================================================================

    /**
     * The unique namespace prefix for this domain state.
     *
     * Rules:
     *  - snake_case only (a-z, 0-9, underscores)
     *  - Non-empty
     *  - Unique across all ApplicationContext subclasses in the application
     *
     * All context keys written by this class are stored as: {prefix}.{key}
     */
    abstract protected function prefix(): string;

    /**
     * Fluent entry point — avoids 'new' at the call site.
     *
     *   OrderState::make()->setOrderId(42)->setStatus('pending');
     */
    final public static function make(): static
    {
        return new static;
    }

    // =========================================================================
    // Public context — written to logs
    // =========================================================================

    /**
     * Store a value in public context under this domain's namespace.
     * Public context is automatically appended to all log entries.
     *
     * Returns $this for fluent chaining.
     */
    final protected function set(string $key, mixed $value): static
    {
        Context::add($this->namespacedKey($key), $value);

        return $this;
    }

    /**
     * Store a value only if the key does not already exist.
     */
    final protected function setIfMissing(string $key, mixed $value): static
    {
        Context::addIf($this->namespacedKey($key), $value);

        return $this;
    }

    /**
     * Retrieve a value from public context.
     */
    final protected function get(string $key, mixed $default = null): mixed
    {
        return Context::get($this->namespacedKey($key), $default);
    }

    /**
     * Retrieve and immediately remove a value from public context.
     */
    final protected function pull(string $key): mixed
    {
        return Context::pull($this->namespacedKey($key));
    }

    /**
     * Increment a numeric counter in public context.
     */
    final protected function increment(string $key, int $amount = 1): static
    {
        Context::increment($this->namespacedKey($key), $amount);

        return $this;
    }

    /**
     * Decrement a numeric counter in public context.
     */
    final protected function decrement(string $key, int $amount = 1): static
    {
        Context::decrement($this->namespacedKey($key), $amount);

        return $this;
    }

    /**
     * Check whether a key exists in public context.
     * Note: returns true even if the stored value is null.
     */
    final protected function has(string $key): bool
    {
        return Context::has($this->namespacedKey($key));
    }

    /**
     * Check whether a key is absent from public context.
     */
    final protected function missing(string $key): bool
    {
        return Context::missing($this->namespacedKey($key));
    }

    /**
     * Remove a key from public context.
     */
    final protected function forget(string $key): static
    {
        Context::forget($this->namespacedKey($key));

        return $this;
    }

    // =========================================================================
    // Hidden context — never written to logs
    // =========================================================================

    /**
     * Store a value in hidden context under this domain's namespace.
     * Hidden context is NEVER written to logs — use for tokens, PII, secrets.
     *
     * Returns $this for fluent chaining.
     */
    final protected function setHidden(string $key, mixed $value): static
    {
        Context::addHidden($this->namespacedKey($key), $value);

        return $this;
    }

    /**
     * Store a hidden value only if the key does not already exist.
     */
    final protected function setHiddenIfMissing(string $key, mixed $value): static
    {
        Context::addHiddenIf($this->namespacedKey($key), $value);

        return $this;
    }

    /**
     * Retrieve a value from hidden context.
     */
    final protected function getHidden(string $key, mixed $default = null): mixed
    {
        return Context::getHidden($this->namespacedKey($key)) ?? $default;
    }

    /**
     * Retrieve and immediately remove a value from hidden context.
     */
    final protected function pullHidden(string $key): mixed
    {
        return Context::pullHidden($this->namespacedKey($key));
    }

    /**
     * Check whether a key exists in hidden context.
     */
    final protected function hasHidden(string $key): bool
    {
        return Context::hasHidden($this->namespacedKey($key));
    }

    /**
     * Check whether a key is absent from hidden context.
     */
    final protected function missingHidden(string $key): bool
    {
        return Context::missingHidden($this->namespacedKey($key));
    }

    /**
     * Remove a key from hidden context.
     */
    final protected function forgetHidden(string $key): static
    {
        Context::forgetHidden($this->namespacedKey($key));

        return $this;
    }

    // =========================================================================
    // Stacks — ordered lists in public context
    // =========================================================================

    /**
     * Push one or more values onto a named stack in public context.
     *
     * Useful for ordered audit trails, breadcrumbs, query logs, etc.
     *
     *   $this->push('breadcrumbs', 'step_one', 'step_two');
     */
    final protected function push(string $key, mixed ...$values): static
    {
        Context::push($this->namespacedKey($key), ...$values);

        return $this;
    }

    /**
     * Pop the last value off a named stack in public context.
     */
    final protected function pop(string $key): mixed
    {
        return Context::pop($this->namespacedKey($key));
    }

    /**
     * Check if a stack in public context contains a given value.
     * Accepts a literal value or a closure for custom comparison logic.
     */
    final protected function stackContains(string $key, mixed $value): bool
    {
        return Context::stackContains($this->namespacedKey($key), $value);
    }

    /**
     * Push one or more values onto a named stack in hidden context.
     */
    final protected function pushHidden(string $key, mixed ...$values): static
    {
        Context::pushHidden($this->namespacedKey($key), ...$values);

        return $this;
    }

    /**
     * Pop the last value off a named stack in hidden context.
     */
    final protected function popHidden(string $key): mixed
    {
        return Context::popHidden($this->namespacedKey($key));
    }

    // =========================================================================
    // Scoped context
    // =========================================================================

    /**
     * Execute a callback with temporary context data that is automatically
     * rolled back when the callback finishes.
     *
     * Mutations to objects inside the callback persist outside the scope.
     * Scalar additions do not.
     *
     * @param  callable(static): void  $callback
     * @param  array<string, mixed>  $data  Temporary public context keys (not namespaced — raw)
     * @param  array<string, mixed>  $hidden  Temporary hidden context keys (not namespaced — raw)
     */
    final public function scope(callable $callback, array $data = [], array $hidden = []): void
    {
        Context::scope(fn () => $callback($this), $data, $hidden);
    }

    // =========================================================================
    // Snapshot — read all state for this domain
    // =========================================================================

    /**
     * Return all public context entries belonging to this domain.
     *
     * Filters the full context down to keys prefixed by this domain's namespace.
     * Useful for debugging, logging a snapshot, or asserting state in tests.
     */
    final public function snapshot(): array
    {
        $prefix = $this->namespacedKey('');

        return collect(Context::all())
            ->filter(fn ($value, $key) => str_starts_with($key, $prefix))
            ->mapWithKeys(fn ($value, $key) => [
                substr($key, strlen($prefix)) => $value,
            ])
            ->all();
    }

    /**
     * Return all hidden context entries belonging to this domain.
     */
    final public function snapshotHidden(): array
    {
        $prefix = $this->namespacedKey('');

        return collect(Context::allHidden())
            ->filter(fn ($value, $key) => str_starts_with($key, $prefix))
            ->mapWithKeys(fn ($value, $key) => [
                substr($key, strlen($prefix)) => $value,
            ])
            ->all();
    }

    /**
     * Remove all public and hidden context entries belonging to this domain.
     */
    final public function flush(): static
    {
        $prefix = $this->namespacedKey('');

        $publicKeys = array_keys(
            array_filter(
                Context::all(),
                fn ($key) => str_starts_with($key, $prefix),
                ARRAY_FILTER_USE_KEY
            )
        );

        $hiddenKeys = array_keys(
            array_filter(
                Context::allHidden(),
                fn ($key) => str_starts_with($key, $prefix),
                ARRAY_FILTER_USE_KEY
            )
        );

        if ($publicKeys) {
            Context::forget($publicKeys);
        }

        if ($hiddenKeys) {
            Context::forgetHidden($hiddenKeys);
        }

        return $this;
    }

    // =========================================================================
    // Internal
    // =========================================================================

    /**
     * Build the namespaced context key: {prefix}.{key}
     *
     * Validates the prefix on first use so misconfigured subclasses
     * fail loudly at runtime rather than silently polluting the context.
     */
    private function namespacedKey(string $key): string
    {
        $prefix = $this->prefix();

        if (empty($prefix)) {
            throw new LogicException(
                static::class.'::prefix() must return a non-empty string.'
            );
        }

        if (! preg_match('/^[a-z0-9_]+$/', $prefix)) {
            throw new LogicException(
                static::class.'::prefix() must be snake_case (a-z, 0-9, underscores only). Got: "'.$prefix.'"'
            );
        }

        return $key === '' ? $prefix.'.' : $prefix.'.'.$key;
    }
}
