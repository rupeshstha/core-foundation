<?php

namespace CoreFoundation\Support;

use UnexpectedValueException;

/**
 * Lang
 *
 * Static translation resolver for contexts where the HasLang trait is not
 * available — exception renderers, form requests, static classes, etc.
 *
 * Controllers should use the HasLang trait's $this->lang() method, which
 * adds domain prefix derivation on top of this class.
 *
 *   // In any class:
 *   Lang::get('core-foundation::http.unauthenticated')
 *
 *   // With replacement parameters:
 *   Lang::get('core-foundation::features.not-found', ['feature' => $name])
 */
class Lang
{
    /**
     * Resolve a fully-qualified translation key.
     *
     * $key must point to a leaf string, not a translation group — trans()
     * returns an array when $key resolves to a whole file/group, which would
     * otherwise silently cast to the literal string "Array".
     *
     * @param  array<string, string>  $replace
     */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        $result = trans($key, $replace, $locale);

        if (! is_string($result)) {
            throw new UnexpectedValueException(
                "Lang::get('{$key}') resolved to a translation group, not a string. Point \$key at a leaf entry."
            );
        }

        return $result;
    }
}
