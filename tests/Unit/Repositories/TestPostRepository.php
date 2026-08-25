<?php

namespace CoreFoundation\Tests\Unit\Repositories;

use CoreFoundation\Repositories\BaseRepository;
use CoreFoundation\Tests\Stubs\Models\TestPost;

class TestPostRepository extends BaseRepository
{
    protected function setModel(): string
    {
        return TestPost::class;
    }

    protected function scopeable(): array
    {
        return ['active', 'ofStatus'];
    }

    /**
     * Regression fixture for the documented "named method starting from
     * $this->query()" pattern — proves query() is still reachable from
     * inside a concrete repository after being locked down to protected.
     */
    public function titlesStartingWith(string $prefix): array
    {
        return $this->query()
            ->where('title', 'like', "{$prefix}%")
            ->pluck('title')
            ->all();
    }
}
