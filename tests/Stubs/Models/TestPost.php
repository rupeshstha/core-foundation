<?php

namespace CoreFoundation\Tests\Stubs\Models;

use CoreFoundation\Entities\BaseModel;

class TestPost extends BaseModel
{
    protected $table = 'test_posts';

    protected $fillable = ['title', 'body', 'status', 'score', 'deleted_at', 'tenant_id'];

    protected static array $searchable = ['title', 'status', 'score', 'deleted_at'];
}
