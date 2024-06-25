<?php

namespace CoreFoundation\Manipulators;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;

abstract class ObjectComposer
{
    protected array $attributes = [];

    public function __set(string $offset, mixed $value): void
    {
        $this->set($offset, $value);
    }

    public function __get(string $offset): mixed
    {
        return $this->get($offset);
    }

    public function __call(string $method, array $arguments): mixed
    {
        return $this->resolveGetterSetter($method, $arguments);
    }

    public function resolveGetterSetter(string $method, array $arguments): mixed
    {
        if (Str::contains($method, "set")) {
            $offset = Str::remove("set", $method);
            $offset = Str::snake($offset);

            $this->set($offset, ...$arguments);
        } elseif (Str::contains($method, "get")) {
            $offset = Str::remove("get", $method);
            $offset = Str::snake($offset);

            return $this->get($offset);
        }

        return null;
    }

    public function get(string $offset, mixed $default = null): mixed
    {
        return $this->attributes[$offset] ?? $default;
    }

    public function set(string $offset, mixed $value = null): self
    {
        $this->attributes[$offset] = $value;

        return $this;
    }

    public function toArray(): array
    {
        return $this->attributes;
    }

    public function reset(): self
    {
        $this->attributes = [];

        return $this;
    }

    public function only(array $attributes): array
    {
        return Arr::only($this->attributes, $attributes);
    }

    public function except(string|array $attributes): array
    {
        return Arr::except($this->attributes, $attributes);
    }
}
