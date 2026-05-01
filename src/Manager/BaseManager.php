<?php

namespace CoreFoundation\Manager;

use Closure;
use CoreFoundation\Contracts\ManagerContract;
use Exception;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;
use InvalidArgumentException;

abstract class BaseManager extends Manager
{
    /**
     * The container instance.
     */
    protected Container $container;

    /**
     * The configuration repository instance.
     */
    protected Repository $config;

    /**
     * The registered custom driver creators.
     */
    protected array $customCreators = [];

    /**
     * The array of created "drivers".
     */
    protected array $drivers = [];

    /**
     * Create a new manager instance.
     *
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->config = $container->make('config');
    }

    /**
     * Get the default driver name.
     */
    abstract public function getDefaultDriver(): string;

    /**
     * Get a driver instance.
     *
     * @param  string|null  $driver
     * @return ManagerContract
     *
     * @throws Exception
     */
    public function driver($driver = null)
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (is_null($driver)) {
            throw new InvalidArgumentException(sprintf(
                'Unable to resolve NULL driver for [%s].', static::class
            ));
        }

        // If the given driver has not been created before, we will create the instances
        // here and cache it so we can return it next time very quickly. If there is
        // already a driver created by this name, we'll just return that instance.
        if (! isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Create a new driver instance.
     *
     *
     * @throws Exception
     */
    protected function createDriver(string $driver): ManagerContract
    {
        // First, we will determine if a custom driver creator exists for the given driver and
        // if it does not we will check for a creator method for the driver. Custom creator
        // callbacks allow developers to build their own "drivers" easily using Closures.
        if (isset($this->customCreators[$driver])) {
            return $this->callCustomCreator($driver);
        } else {
            $method = 'create'.Str::studly($driver).'Driver';

            if (method_exists($this, $method)) {
                return $this->{$method}();
            }
        }

        throw new Exception("Driver [$driver] not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param  string  $driver
     */
    protected function callCustomCreator($driver): ManagerContract
    {
        return $this->customCreators[$driver]($this->container);
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param  string  $driver
     */
    public function extend($driver, Closure $callback): self
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Get all of the created "drivers".
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * Get the container instance used by the manager.
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Set the container instance used by the manager.
     */
    public function setContainer(Container $container): self
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Forget all of the resolved driver instances.
     */
    public function forgetDrivers(): self
    {
        $this->drivers = [];

        return $this;
    }

    /**
     * Dynamically call the default driver instance.
     */
    public function __call(string $method, mixed $parameters): mixed
    {
        return $this->driver()->{$method}(...$parameters);
    }
}
