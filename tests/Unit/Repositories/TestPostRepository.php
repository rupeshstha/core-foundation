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
}
