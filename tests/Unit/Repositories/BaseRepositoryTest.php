<?php

namespace CoreFoundation\Tests\Unit\Repositories;

use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Repositories\BaseRepository;
use CoreFoundation\Tests\Stubs\Models\TestPost;
use CoreFoundation\Exceptions\StaleDataException;

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

    public function test_it_applies_pessimistic_lock_for_update(): void
    {
        $post = TestPost::create(['title' => 'Post 1']);

        $result = $this->repository->lockForUpdate()->fetchById($post->id);

        $this->assertNotNull($result);
        
        $reflection = new \ReflectionClass($this->repository);
        $property = $reflection->getProperty('lockMode');
        $property->setAccessible(true);
        $this->assertFalse($property->getValue($this->repository));
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

    public function test_update_atomic_succeeds_on_matching_conditions(): void
    {
        $post = TestPost::create(['title' => 'Atomic', 'status' => 'pending']);

        $updated = $this->repository->updateAtomic($post->id, ['title' => 'Changed'], ['status' => 'pending']);

        $this->assertEquals('Changed', $updated->title);
        $this->assertDatabaseHas('test_posts', ['title' => 'Changed']);
    }

    public function test_update_atomic_fails_on_condition_mismatch(): void
    {
        $post = TestPost::create(['title' => 'Atomic', 'status' => 'published']);

        $this->expectException(StaleDataException::class);
        // Try to update assuming it's still 'pending'
        $this->repository->updateAtomic($post->id, ['title' => 'Changed'], ['status' => 'pending']);
    }

    public function test_update_atomic_can_implement_version_locking_opt_in(): void
    {
        $post = TestPost::create(['title' => 'Versioned', 'version' => 1]);

        $updated = $this->repository->updateAtomic(
            id: $post->id, 
            attributes: ['title' => 'New', 'version' => 2], 
            conditions: ['version' => 1]
        );

        $this->assertEquals(2, $updated->version);
    }

    public function test_it_can_delete_model(): void
    {
        $post = TestPost::create(['title' => 'To be deleted']);

        $result = $this->repository->delete($post->id);

        $this->assertTrue($result);
        $this->assertDatabaseMissing('test_posts', ['id' => $post->id]);
    }
}
