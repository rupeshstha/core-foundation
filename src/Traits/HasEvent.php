<?php

namespace CoreFoundation\Traits;

use Illuminate\Support\Facades\Event;

trait HasEvent
{
    protected ?string $eventPrefix = null;

    protected bool $eventDispatch = true;

    protected static array $interceptors = [];

    public function eventDispatch(string $eventKey, mixed $data = [], bool $restrictEventPrefix = false): void
    {
        if ($this->eventPrefix && ! $restrictEventPrefix) {
            $eventKey = "{$this->eventPrefix}.{$eventKey}";
        }

        if ($this->eventDispatch) {
            Event::dispatch($eventKey, $data);
        }
    }

    /**
     * This method convert code flow to interceptor design pattern.
     *
     * @return void
     */
    public function interceptorEventDispatch(string $eventKey, mixed $data = [], bool $restrictEventPrefix = false)
    {
        $backtrace = last(debug_backtrace(limit: 2));
        $previousFunction = $backtrace['function'];
        $previousClass = $backtrace['class'];
        $previousInstance = $backtrace['object'];
        $previousArguments = $backtrace['args'];

        $interceptor = $this->getInterceptor($previousClass);
        if ($interceptor) {
            $dotPosition = strrpos($eventKey, '.');
            /**
             * if your event name is "user.index.before", "user-role.index.before" etc
             * It will only take last key as reference to interceptor before or after event name.
             * Its better if we separate before, after and around event dispatch method instead. 🤔
             */
            $lastKeyAfterDot = ucfirst(substr($eventKey, $dotPosition + 1));
            $interceptedObject = resolve($interceptor['interceptTo'], [$previousInstance]);
            $data = is_array($data) ? $data : [$data];
            $interceptedObject->{$previousFunction.$lastKeyAfterDot}(array_merge($previousArguments, $data));
        }
    }

    public function getInterceptor(string $interceptorClass): ?array
    {
        /**
         * Maintained static properties for performance wise.
         *
         * TODO:: Make a flexibility to add/remove interceptors from service provider. It should be in register method.
         */
        if (! count(static::$interceptors)) {
            static::$interceptors = $interceptors = config('interceptors', []);
        }
        $interceptors = static::$interceptors;

        usort($interceptors, function (array $current, array $next) {
            $compareFrom = strcmp($current['interceptFrom'], $next['interceptFrom']);
            if ($compareFrom === 0) {
                return $current['priority'] <=> $next['priority'];
            }

            return $compareFrom;
        });

        foreach ($interceptors as $interceptor) {
            if ($interceptor['interceptFrom'] === $interceptorClass) {
                return $interceptor;
            }
        }

        return null;
    }
}
