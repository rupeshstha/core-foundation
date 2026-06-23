<?php

namespace CoreFoundation\Tests\Stubs\Models;

use Illuminate\Database\Eloquent\Builder;
use CoreFoundation\Entities\BaseModel;

class TestPost extends BaseModel
{
    protected $table = 'test_posts';

    protected $fillable = ['title', 'body', 'status', 'score', 'version', 'deleted_at', 'tenant_id'];

    protected static array $searchable = ['title', 'status', 'score', 'deleted_at'];

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', 'active');
    }

    public function scopeOfStatus(Builder $query, string $status): Builder
    {
        return $query->where('status', $status);
    }

    public function scopeFromModule(Builder $query): Builder
    {
        return $query->where('score', '>', 10);
    }
}
