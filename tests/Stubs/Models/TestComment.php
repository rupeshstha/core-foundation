<?php

namespace CoreFoundation\Tests\Stubs\Models;

use CoreFoundation\Entities\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TestComment extends BaseModel
{
    protected $table = 'test_comments';

    protected $fillable = ['test_post_id', 'body'];

    public function post(): BelongsTo
    {
        return $this->belongsTo(TestPost::class);
    }
}
