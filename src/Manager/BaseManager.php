<?php

namespace CoreFoundation\Manager;

use Closure;
use Exception;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Container\Container;
use CoreFoundation\Contracts\ManagerContract;
use Illuminate\Support\Manager;
use Illuminate\Support\Str;

abstract class BaseManager extends Manager
{
    /**
     * The container instance.
     *
     * @var Container
     */
    protected Container $container;

    /**
     * The configuration repository instance.
     *
     * @var Repository
     */
    protected Repository $config;

    /**
     * The registered custom driver creators.
     *
     * @var array
     */
    protected array $customCreators = [];

    /**
     * The array of created "drivers".
     *
     * @var array
     */
    protected array $drivers = [];

    /**
     * Create a new manager instance.
     *
     * @param Container $container
     * @return void
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->config = $container->make("config");
    }

    /**
     * Get the default driver name.
     *
     * @return string
     */
    abstract public function getDefaultDriver(): string;

    /**
     * Get a driver instance.
     *
     * @param string|null $driver
     * @return ManagerContract
     *
     * @throws Exception
     */
    public function driver(?string $driver = null): ManagerContract
    {
        $driver = $driver ?: $this->getDefaultDriver();

        if (is_null($driver)) {
            throw new Exception(sprintf(
                "Unable to resolve NULL driver for [%s].", static::class
            ));
        }

        // If the given driver has not been created before, we will create the instances
        // here and cache it so we can return it next time very quickly. If there is
        // already a driver created by this name, we"ll just return that instance.
        if (! isset($this->drivers[$driver])) {
            $this->drivers[$driver] = $this->createDriver($driver);
        }

        return $this->drivers[$driver];
    }

    /**
     * Create a new driver instance.
     *
     * @param string $driver
     * @return ManagerContract
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
            $method = "create" . Str::studly($driver) . "Driver";

            if (method_exists($this, $method)) {
                return $this->{$method}();
            }
        }

        throw new Exception("Driver [$driver] not supported.");
    }

    /**
     * Call a custom driver creator.
     *
     * @param string $driver
     * @return ManagerContract
     */
    protected function callCustomCreator($driver): ManagerContract
    {
        return $this->customCreators[$driver]($this->container);
    }

    /**
     * Register a custom driver creator Closure.
     *
     * @param string $driver
     * @param Closure $callback
     * @return self
     */
    public function extend($driver, Closure $callback): self
    {
        $this->customCreators[$driver] = $callback;

        return $this;
    }

    /**
     * Get all of the created "drivers".
     *
     * @return array
     */
    public function getDrivers(): array
    {
        return $this->drivers;
    }

    /**
     * Get the container instance used by the manager.
     *
     * @return Container
     */
    public function getContainer(): Container
    {
        return $this->container;
    }

    /**
     * Set the container instance used by the manager.
     *
     * @param Container $container
     * @return self
     */
    public function setContainer(Container $container): self
    {
        $this->container = $container;

        return $this;
    }

    /**
     * Forget all of the resolved driver instances.
     *
     * @return self
     */
    public function forgetDrivers(): self
    {
        $this->drivers = [];

        return $this;
    }

    /**
     * Dynamically call the default driver instance.
     *
     * @param string $method
     * @param mixed $parameters
     *
     * @return mixed
     */
    public function __call(string $method, mixed $parameters): mixed
    {
        return $this->driver()->{$method}(...$parameters);
    }
}
