<?php

namespace CoreFoundation\Traits;

/**
 * HasLang
 *
 * Controller-scoped translation helper.
 *
 * Provides $this->lang() which resolves translation keys relative to a
 * namespace prefix derived from the controller class name, so translation
 * files stay co-located with the domain they belong to.
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ DEFAULT NAMESPACE CONVENTION                                                │
 * │                                                                             │
 * │ The prefix is derived from the controller class name:                       │
 * │   UserController   → 'user'                                                 │
 * │   OrderController  → 'order'                                                │
 * │   ProductController → 'product'                                             │
 * │                                                                             │
 * │ So $this->lang('fetch-success') in UserController resolves to:              │
 * │   trans('user.fetch-success')                                               │
 * │                                                                             │
 * │ Which maps to: lang/en/user.php → ['fetch-success' => 'Users fetched.']    │
 * │                                                                             │
 * │ Override langPrefix() to use a custom namespace:                            │
 * │                                                                             │
 * │   protected function langPrefix(): string                                   │
 * │   {                                                                         │
 * │       return 'orders';                                                      │
 * │   }                                                                         │
 * └─────────────────────────────────────────────────────────────────────────────┘
 *
 * ┌─────────────────────────────────────────────────────────────────────────────┐
 * │ BYPASSING THE PREFIX                                                        │
 * │                                                                             │
 * │ Pass a key with a dot to use a fully-qualified translation key:             │
 * │                                                                             │
 * │   $this->lang('errors.not-found')   → trans('errors.not-found')            │
 * │   $this->lang('fetch-success')      → trans('user.fetch-success')          │
 * └─────────────────────────────────────────────────────────────────────────────┘
 */
trait HasLang
{
    /**
     * Resolve a translation key, scoped to this controller's domain prefix.
     *
     * Keys containing a dot are treated as fully-qualified and bypass the prefix.
     *
     * @param  string  $key  Translation key e.g. 'fetch-success'
     * @param  array  $replace  Substitution variables passed to trans()
     * @param  string|null  $locale  Override locale for this call
     */
    final protected function lang(string $key, array $replace = [], ?string $locale = null): string
    {
        $resolved = str_contains($key, '.')
            ? $key
            : $this->langPrefix().'.'.$key;

        return (string) trans($resolved, $replace, $locale);
    }

    /**
     * The translation namespace prefix for this controller.
     * Derived from the controller class name by default — override to customise.
     */
    protected function langPrefix(): string
    {
        $class = class_basename(static::class);

        // Strip "Controller" suffix: UserController → user
        return strtolower(
            str_ends_with($class, 'Controller')
                ? substr($class, 0, -10)
                : $class
        );
    }
}
