<?php

namespace CoreFoundation\Traits;

use CoreFoundation\Support\Lang;

/**
 * HasLang
 *
 * Controller-scoped translation helper.
 *
 * Resolves translation keys relative to a namespace prefix derived from the
 * controller class name. For translation outside controllers, use Lang::get().
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE IN CONTROLLERS                                                        │
 * │                                                                             │
 * │   $this->lang('fetch-success')                                              │
 * │   // UserController → trans('user.fetch-success')                           │
 * │                                                                             │
 * │   $this->lang('core-foundation::http.not-found')                            │
 * │   // key contains dot → bypasses prefix → trans('core-foundation::http.not-found')
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ USAGE OUTSIDE CONTROLLERS                                                   │
 * │                                                                             │
 * │   Use Lang::get() — a proper static class, not this trait:                  │
 * │                                                                             │
 * │   Lang::get('core-foundation::http.unauthenticated')                        │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ OVERRIDING THE PREFIX                                                       │
 * │                                                                             │
 * │   protected function langPrefix(): string                                   │
 * │   {                                                                         │
 * │       return 'orders';                                                      │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait HasLang
{
    /**
     * Resolve a translation key scoped to this controller's domain prefix.
     *
     * Keys containing a dot are treated as fully-qualified and bypass the prefix:
     *   'fetch-success'                   → trans('{prefix}.fetch-success')
     *   'core-foundation::http.not-found' → trans('core-foundation::http.not-found')
     */
    final protected function lang(string $key, array $replace = [], ?string $locale = null): string
    {
        $resolved = str_contains($key, '.')
            ? $key
            : $this->langPrefix().'.'.$key;

        return Lang::get($resolved, $replace, $locale);
    }

    /**
     * The translation namespace prefix for this controller.
     * Derived from the class name by default — override to customise.
     *
     * UserController  → 'user'
     * OrderController → 'order'
     */
    protected function langPrefix(): string
    {
        $class = class_basename(static::class);

        return strtolower(
            str_ends_with($class, 'Controller')
                ? substr($class, 0, -10)
                : $class
        );
    }
}
