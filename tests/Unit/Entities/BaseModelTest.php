<?php

namespace CoreFoundation\Tests\Unit\Entities;

use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Tests\Stubs\Models\TestPost;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BaseModelTest extends PackageTestCase
{
    public function test_it_can_add_fillable_at_runtime(): void
    {
        TestPost::addFillable(['extra_field']);

        $post = new TestPost;
        $this->assertTrue($post->isFillable('extra_field'));
    }

    public function test_it_can_add_casts_at_runtime(): void
    {
        TestPost::addFillable(['is_published']);
        TestPost::addCast(['is_published' => 'boolean']);

        $post = new TestPost(['is_published' => 1]);
        $this->assertEquals('boolean', $post->getCasts()['is_published']);
        $this->assertTrue($post->is_published);
    }

    public function test_it_can_add_relations_at_runtime(): void
    {
        TestPost::addRelation('related_post', function ($post) {
            return $post->belongsTo(TestPost::class, 'parent_id');
        });

        $post = new TestPost;
        $this->assertTrue(TestPost::hasBindRelation('related_post'));
        $this->assertInstanceOf(BelongsTo::class, $post->related_post());
    }

    public function test_it_can_add_searchable_at_runtime(): void
    {
        TestPost::addSearchable(['body']);

        $this->assertContains('body', TestPost::getSearchable());
    }
}
