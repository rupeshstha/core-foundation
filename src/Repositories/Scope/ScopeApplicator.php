<?php

namespace CoreFoundation\Repositories\Scope;

use Illuminate\Database\Eloquent\Builder;

/**
 * ScopeApplicator
 *
 * Applies Eloquent local (named) scopes to a Builder — request-driven,
 * whitelist-gated. Separate class from FilterApplicator/SortApplicator —
 * unified request, separate concern.
 * Pure — takes an array of scope names, returns a mutated Builder.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ SCOPE FORMAT — matches Eloquent's native Builder::scopes() input exactly    │
 * │                                                                             │
 * │ Request: scopes[]=active&scopes[]=recent                                    │
 * │                                                                             │
 * │   scopes[]=active                → Model::scopeActive()                    │
 * │   scopes[]=active&scopes[]=recent → scopeActive() + scopeRecent()          │
 * │                                                                             │
 * │ With arguments, as key=>args pairs:                                         │
 * │   scopes[ofType][]=admin         → Model::scopeOfType(['admin'])           │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ WHY A WHITELIST                                                            │
 * │                                                                             │
 * │ Eloquent's Builder::scopes() calls callNamedScope() with no existence      │
 * │ guard — an unknown scope name throws BadMethodCallException. Allowing      │
 * │ request input to invoke any scopeXxx() method by name would also expose   │
 * │ scopes never meant for direct external use. The whitelist closes both     │
 * │ gaps: unrecognised names are silently skipped, matching the same           │
 * │ "never throw on invalid request input" rule FilterApplicator/             │
 * │ SortApplicator already follow.                                             │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
final class ScopeApplicator
{
    /**
     * Apply whitelisted named scopes to the given Builder.
     *
     * @param  Builder  $builder  The query to scope
     * @param  array  $scopes  Scope params from request — ['active', 'recent'] or ['ofType' => ['admin']]
     * @param  array<string>  $allowedScopes  Scope name whitelist
     */
    public function apply(Builder $builder, array $scopes, array $allowedScopes): Builder
    {
        $allowed = $this->filterAllowed($scopes, $allowedScopes);

        if ($allowed === []) {
            return $builder;
        }

        return $builder->scopes($allowed);
    }

    // =========================================================================
    // Internals
    // =========================================================================

    /**
     * Reduce the requested scopes to only those present in the whitelist.
     * Preserves Eloquent's two input shapes: indexed (name only) and
     * associative (name => arguments).
     */
    private function filterAllowed(array $scopes, array $allowedScopes): array
    {
        $allowed = [];

        foreach ($scopes as $key => $value) {
            $name = is_int($key) ? $value : $key;

            if (! in_array($name, $allowedScopes, true)) {
                continue; // Security — reject scopes not in whitelist
            }

            if (is_int($key)) {
                $allowed[] = $value;
            } else {
                $allowed[$key] = $value;
            }
        }

        return $allowed;
    }
}
