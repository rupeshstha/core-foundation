<?php

namespace CoreFoundation\Services;

use CoreFoundation\Contracts\StrategyContract;
use Illuminate\Support\Collection;
use Illuminate\Support\Fluent;

class StrategyService implements StrategyContract
{
    private function hydrate(array $attributes = []): Fluent
    {
        return new Fluent($attributes);
    }

    private function getStrategy(): Collection
    {
        return collect(config('strategy'));
    }

    public function get(string $type, ?string $identifier): Fluent
    {
        $strategies = $this->getStrategy();
        $strategy = $strategies->where('type', $type)
            ->sortBy('priority', descending: true)
            ->where('identifier', $identifier)
            ->first();

        if (! $strategy) {
            $strategy = $strategies->where('type', $type)
                ->sortBy('priority', descending: true)
                ->where('default', true)
                ->first();
        }

        return $this->hydrate($strategy ?? []);
    }

    public function instance(string $type, ?string $identifier): mixed
    {
        $strategy = $this->get($type, $identifier);
        $class = $strategy->class;
        $parameters = $strategy->parameters ?? [];
        $instance = resolve(
            name: $class,
            parameters: $parameters
        );

        return $instance;
    }
}
