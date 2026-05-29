<?php

namespace CoreFoundation\Tests\Unit\Repositories;

use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Repositories\BaseRepository;
use CoreFoundation\Tests\Stubs\Models\TestPost;

class TestPostRepository extends BaseRepository
{
    protected function setModel(): string
    {
        return TestPost::class;
    }
}

class BaseRepositoryTest extends PackageTestCase
{
    private TestPostRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(TestPostRepository::class);
    }

    public function test_it_can_fetch_all_with_filters(): void
    {
        TestPost::create(['title' => 'Post 1', 'status' => 'active']);
        TestPost::create(['title' => 'Post 2', 'status' => 'pending']);

        $results = $this->repository->fetchAll(['filters' => ['__eq_status' => 'active']], paginate: false);

        $this->assertCount(1, $results);
        $this->assertEquals('Post 1', $results->first()->title);
    }

    public function test_it_can_fetch_all_with_sorting(): void
    {
        TestPost::create(['title' => 'A', 'status' => 'active']);
        TestPost::create(['title' => 'B', 'status' => 'active']);

        $results = $this->repository->fetchAll([
            'filters' => ['__eq_status' => 'active'],
            'sort' => ['-title'],
        ], paginate: false);

        $this->assertCount(2, $results);
        $this->assertEquals('B', $results->first()->title);
    }

    public function test_it_can_fetch_by_id(): void
    {
        $post = TestPost::create(['title' => 'Post 1']);

        $result = $this->repository->fetchById($post->id);

        $this->assertNotNull($result);
        $this->assertEquals($post->id, $result->id);
    }

    public function test_it_can_create_model(): void
    {
        $post = $this->repository->create(['title' => 'New Post']);

        $this->assertDatabaseHas('test_posts', ['title' => 'New Post']);
        $this->assertEquals('New Post', $post->title);
    }

    public function test_it_can_update_model(): void
    {
        $post = TestPost::create(['title' => 'Old Title']);

        $updated = $this->repository->update($post->id, ['title' => 'New Title']);

        $this->assertEquals('New Title', $updated->title);
        $this->assertDatabaseHas('test_posts', ['title' => 'New Title']);
    }

    public function test_it_can_delete_model(): void
    {
        $post = TestPost::create(['title' => 'To be deleted']);

        $result = $this->repository->delete($post->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('test_posts', ['id' => $post->id]);
    }
}
