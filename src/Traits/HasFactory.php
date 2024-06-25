<?php

namespace CoreFoundation\Traits;

use Closure;

trait HasFactory
{
    protected static array $factories = [];
    protected static array $factoryConditions = [];

    public static function setFactory(string $concrete, int $priority = 0): void
    {
        static::$factories[self::class] = $concrete;
    }

    public static function setFactoryCondition(string $targetConcrete, string|Closure $condition): void
    {
        if (isset(static::$factories[self::class])) {
            static::$factoryConditions[$targetConcrete] = $condition;
        }
    }

    public function factory(): mixed
    {
        if (isset(static::$factories[self::class])) {
            $concrete = static::$factories[self::class];
            $condition = static::$factoryConditions[$concrete];
            if ($condition instanceof Closure) {
                $condition = $condition();
            } else {
                /**
                 * ! If the condition is string then it will search the binded object in service container.
                 * ! It should be singleton to service container.
                 *
                 * TODO: add a flexibility to pass class arguments.
                 */
                $condition = resolve($condition)->handle();
            }

            if ($condition) {
                return resolve($concrete); // TODO: add a flexibility to pass class arguments.
            }
        }

        return $this;
    }
}
