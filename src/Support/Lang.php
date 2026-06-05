<?php

namespace CoreFoundation\Support;

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
     * @param  array<string, string>  $replace
     */
    public static function get(string $key, array $replace = [], ?string $locale = null): string
    {
        return (string) trans($key, $replace, $locale);
    }
}
