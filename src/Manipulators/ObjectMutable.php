<?php

namespace CoreFoundation\Manipulators;

class ObjectMutable extends ObjectComposer
{
    public function create(array $attributes = []): self
    {
        $this->attributes = $attributes;

        return $this;
    }
}
