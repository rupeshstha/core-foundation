<?php

namespace CoreFoundation\Tests\Unit\Repositories;

use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Repositories\BaseRepository;
use CoreFoundation\Tests\Stubs\Models\TestPost;
use CoreFoundation\Tests\Stubs\Models\TestComment;

class RelationCachePostRepository extends BaseRepository
{
    protected function setModel(): string
    {
        return TestPost::class;
    }
}

class RelationCacheCommentRepository extends BaseRepository
{
    protected function setModel(): string
    {
        return TestComment::class;
    }
}

class RelationCacheInvalidationTest extends PackageTestCase
{
    private RelationCachePostRepository $postRepository;

    private RelationCacheCommentRepository $commentRepository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->postRepository = $this->app->make(RelationCachePostRepository::class);
        $this->commentRepository = $this->app->make(RelationCacheCommentRepository::class);
    }

    public function test_creating_a_related_record_busts_the_eager_loading_models_cache(): void
    {
        $post = TestPost::create(['title' => 'Post 1']);
        TestComment::create(['test_post_id' => $post->id, 'body' => 'First comment']);

        $before = $this->postRepository->with('comments')->fetchAll(paginate: false);
        $this->assertCount(1, $before->first()->comments);

        $this->commentRepository->create(['test_post_id' => $post->id, 'body' => 'Second comment']);

        $after = $this->postRepository->with('comments')->fetchAll(paginate: false);
        $this->assertCount(2, $after->first()->comments);
    }

    public function test_updating_a_related_record_busts_the_eager_loading_models_cache(): void
    {
        $post = TestPost::create(['title' => 'Post 1']);
        $comment = TestComment::create(['test_post_id' => $post->id, 'body' => 'Original']);

        $before = $this->postRepository->with('comments')->fetchAll(paginate: false);
        $this->assertEquals('Original', $before->first()->comments->first()->body);

        $this->commentRepository->update($comment->id, ['body' => 'Edited']);

        $after = $this->postRepository->with('comments')->fetchAll(paginate: false);
        $this->assertEquals('Edited', $after->first()->comments->first()->body);
    }

    public function test_deleting_a_related_record_busts_the_eager_loading_models_cache(): void
    {
        $post = TestPost::create(['title' => 'Post 1']);
        $comment = TestComment::create(['test_post_id' => $post->id, 'body' => 'To delete']);

        $before = $this->postRepository->with('comments')->fetchAll(paginate: false);
        $this->assertCount(1, $before->first()->comments);

        $this->commentRepository->delete($comment->id);

        $after = $this->postRepository->with('comments')->fetchAll(paginate: false);
        $this->assertCount(0, $after->first()->comments);
    }

    public function test_a_query_that_does_not_eager_load_the_relation_is_unaffected_by_related_writes(): void
    {
        $post = TestPost::create(['title' => 'Post 1']);
        TestComment::create(['test_post_id' => $post->id, 'body' => 'First comment']);

        // Warm a cache entry that does NOT eager-load comments.
        $before = $this->postRepository->fetchAll(paginate: false);
        $this->assertFalse($before->first()->relationLoaded('comments'));

        // A related write must not need to touch this entry — it never embedded comments.
        $this->commentRepository->create(['test_post_id' => $post->id, 'body' => 'Second comment']);

        $after = $this->postRepository->fetchAll(paginate: false);
        $this->assertFalse($after->first()->relationLoaded('comments'));
    }

    public function test_writes_to_an_unrelated_models_record_cache_stay_isolated(): void
    {
        $post1 = TestPost::create(['title' => 'Post 1']);
        $post2 = TestPost::create(['title' => 'Post 2']);

        $this->postRepository->fetchById($post1->id);
        $this->postRepository->fetchById($post2->id);

        $this->postRepository->update($post1->id, ['title' => 'Post 1 Updated']);

        $this->assertEquals('Post 1 Updated', $this->postRepository->fetchById($post1->id)->title);
        $this->assertEquals('Post 2', $this->postRepository->fetchById($post2->id)->title);
    }
}
