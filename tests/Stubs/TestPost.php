<?php

namespace CoreFoundation\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;

class TestPost extends Model
{
    protected $table = 'test_posts';
    protected $guarded = [];
}
