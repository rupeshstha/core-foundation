<?php

namespace CoreFoundation\Tests\Unit\Repositories;

use ReflectionClass;
use BadMethodCallException;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Tests\Stubs\Models\TestPost;
use CoreFoundation\Exceptions\StaleDataException;
use CoreFoundation\Tests\Stubs\Models\TestComment;
use Illuminate\Database\Eloquent\ModelNotFoundException;

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

    public function test_it_can_fetch_all_with_a_whitelisted_scope(): void
    {
        TestPost::create(['title' => 'Post 1', 'status' => 'active']);
        TestPost::create(['title' => 'Post 2', 'status' => 'pending']);

        $results = $this->repository->fetchAll(['scopes' => ['active']], paginate: false);

        $this->assertCount(1, $results);
        $this->assertEquals('Post 1', $results->first()->title);
    }

    public function test_it_can_fetch_all_with_a_scope_that_takes_arguments(): void
    {
        TestPost::create(['title' => 'Post 1', 'status' => 'active']);
        TestPost::create(['title' => 'Post 2', 'status' => 'pending']);

        $results = $this->repository->fetchAll(['scopes' => ['ofStatus' => ['pending']]], paginate: false);

        $this->assertCount(1, $results);
        $this->assertEquals('Post 2', $results->first()->title);
    }

    public function test_it_silently_skips_a_scope_not_in_the_repository_whitelist(): void
    {
        TestPost::create(['title' => 'Post 1', 'status' => 'active']);
        TestPost::create(['title' => 'Post 2', 'status' => 'pending']);

        // 'archived' is not returned by TestPostRepository::scopeable()
        $results = $this->repository->fetchAll(['scopes' => ['archived']], paginate: false);

        $this->assertCount(2, $results);
    }

    public function test_it_can_fetch_by_id(): void
    {
        $post = TestPost::create(['title' => 'Post 1']);

        $result = $this->repository->fetchById($post->id);

        $this->assertNotNull($result);
        $this->assertEquals($post->id, $result->id);
    }

    public function test_it_can_apply_a_fluent_scope_to_fetch_by_id(): void
    {
        $post = TestPost::create(['title' => 'Post 1', 'status' => 'active']);

        $result = $this->repository->scope('active')->fetchById($post->id);

        $this->assertEquals($post->id, $result->id);
    }

    public function test_a_fluent_scope_excludes_non_matching_records_on_fetch_by_id(): void
    {
        $post = TestPost::create(['title' => 'Post 1', 'status' => 'pending']);

        $this->expectException(ModelNotFoundException::class);
        $this->repository->scope('active')->fetchById($post->id);
    }

    public function test_it_can_apply_a_fluent_scope_to_fetch_all(): void
    {
        TestPost::create(['title' => 'Post 1', 'status' => 'active']);
        TestPost::create(['title' => 'Post 2', 'status' => 'pending']);

        $results = $this->repository->scope('active')->fetchAll(paginate: false);

        $this->assertCount(1, $results);
        $this->assertEquals('Post 1', $results->first()->title);
    }

    public function test_a_fluent_scope_accepts_arguments(): void
    {
        TestPost::create(['title' => 'Post 1', 'status' => 'active']);
        TestPost::create(['title' => 'Post 2', 'status' => 'pending']);

        $results = $this->repository->scope('ofStatus', ['pending'])->fetchAll(paginate: false);

        $this->assertCount(1, $results);
        $this->assertEquals('Post 2', $results->first()->title);
    }

    public function test_fluent_scopes_can_be_chained(): void
    {
        TestPost::create(['title' => 'Post 1', 'status' => 'active', 'score' => 5]);
        TestPost::create(['title' => 'Post 2', 'status' => 'active', 'score' => 1]);

        $results = $this->repository
            ->scope('active')
            ->scope('ofStatus', ['active'])
            ->fetchAll(paginate: false);

        $this->assertCount(2, $results);
    }

    public function test_a_fluent_scope_is_not_whitelist_gated_and_throws_on_an_unknown_name(): void
    {
        $this->expectException(BadMethodCallException::class);
        $this->repository->scope('nonexistentScope')->fetchAll(paginate: false);
    }

    public function test_a_fluent_scope_only_applies_to_the_next_call(): void
    {
        TestPost::create(['title' => 'Post 1', 'status' => 'active']);
        TestPost::create(['title' => 'Post 2', 'status' => 'pending']);

        $scoped = $this->repository->scope('active')->fetchAll(paginate: false);
        $unscoped = $this->repository->fetchAll(paginate: false);

        $this->assertCount(1, $scoped);
        $this->assertCount(2, $unscoped);
    }

    public function test_a_fluent_scope_does_not_poison_the_cache_for_unscoped_calls(): void
    {
        TestPost::create(['title' => 'Post 1', 'status' => 'active']);
        TestPost::create(['title' => 'Post 2', 'status' => 'pending']);

        // Warm the unscoped cache entry first.
        $before = $this->repository->fetchAll(paginate: false);
        $this->assertCount(2, $before);

        // A scoped call must hit a different cache key, not the one just warmed.
        $scoped = $this->repository->scope('active')->fetchAll(paginate: false);
        $this->assertCount(1, $scoped);

        // The original unscoped entry must still be intact.
        $after = $this->repository->fetchAll(paginate: false);
        $this->assertCount(2, $after);
    }

    public function test_it_can_apply_a_fluent_eager_load_to_fetch_by_id(): void
    {
        $post = TestPost::create(['title' => 'Post 1']);
        TestComment::create(['test_post_id' => $post->id, 'body' => 'First comment']);

        $result = $this->repository->with('comments')->fetchById($post->id);

        $this->assertTrue($result->relationLoaded('comments'));
        $this->assertCount(1, $result->comments);
    }

    public function test_it_can_apply_a_fluent_eager_load_to_fetch_all(): void
    {
        $post = TestPost::create(['title' => 'Post 1']);
        TestComment::create(['test_post_id' => $post->id, 'body' => 'First comment']);

        $results = $this->repository->with(['comments'])->fetchAll(paginate: false);

        $this->assertTrue($results->first()->relationLoaded('comments'));
    }

    public function test_a_fluent_eager_load_merges_with_the_explicit_relations_argument(): void
    {
        $post = TestPost::create(['title' => 'Post 1']);
        TestComment::create(['test_post_id' => $post->id, 'body' => 'First comment']);

        $result = $this->repository->with('comments')->fetchById($post->id, relations: ['comments']);

        $this->assertTrue($result->relationLoaded('comments'));
    }

    public function test_a_fluent_eager_load_only_applies_to_the_next_call(): void
    {
        $post = TestPost::create(['title' => 'Post 1']);
        TestComment::create(['test_post_id' => $post->id, 'body' => 'First comment']);

        $loaded = $this->repository->with('comments')->fetchById($post->id);
        $notLoaded = $this->repository->fetchById($post->id);

        $this->assertTrue($loaded->relationLoaded('comments'));
        $this->assertFalse($notLoaded->relationLoaded('comments'));
    }

    public function test_a_fluent_eager_load_does_not_poison_the_cache_for_calls_without_it(): void
    {
        $post = TestPost::create(['title' => 'Post 1']);
        TestComment::create(['test_post_id' => $post->id, 'body' => 'First comment']);

        // Warm the cache entry without eager loading first.
        $before = $this->repository->fetchById($post->id);
        $this->assertFalse($before->relationLoaded('comments'));

        // A call with an eager load must hit a different cache key.
        $withRelation = $this->repository->with('comments')->fetchById($post->id);
        $this->assertTrue($withRelation->relationLoaded('comments'));

        // The original entry must still be intact — not poisoned by the eager load.
        $after = $this->repository->fetchById($post->id);
        $this->assertFalse($after->relationLoaded('comments'));
    }

    public function test_it_applies_pessimistic_lock_for_update(): void
    {
        $post = TestPost::create(['title' => 'Post 1']);

        $result = $this->repository->lockForUpdate()->fetchById($post->id);

        $this->assertNotNull($result);

        $reflection = new ReflectionClass($this->repository);
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

    public function test_query_remains_usable_from_a_named_method_on_the_concrete_repository(): void
    {
        TestPost::create(['title' => 'Alpha Post']);
        TestPost::create(['title' => 'Alpha Report']);
        TestPost::create(['title' => 'Beta Post']);

        $titles = $this->repository->titlesStartingWith('Alpha');

        $this->assertEqualsCanonicalizing(['Alpha Post', 'Alpha Report'], $titles);
    }

    public function test_query_is_not_part_of_the_public_api(): void
    {
        // query() is the entry point for custom queries defined as named
        // methods ON a concrete repository — never callable from a service
        // or controller. Asserting this stays protected is a regression
        // guard: widening it back to public silently reopens the exact
        // cache-bypass footgun the method's docblock warns about.
        $method = (new ReflectionClass($this->repository))->getMethod('query');

        $this->assertTrue($method->isProtected());
    }

    public function test_quiet_update_updates_the_record_without_flushing_cache(): void
    {
        $post = TestPost::create(['title' => 'Original Title']);

        // Warm the fetchById cache entry.
        $cached = $this->repository->fetchById($post->id);
        $this->assertEquals('Original Title', $cached->title);

        $this->repository->update($post->id, ['title' => 'Changed Silently'], quiet: true);

        // The write reached the database...
        $this->assertDatabaseHas('test_posts', ['id' => $post->id, 'title' => 'Changed Silently']);

        // ...but the cached read was never invalidated, so it keeps serving
        // the pre-update value. This is the documented trade-off, not a bug —
        // quiet: true exists specifically to skip this invalidation.
        $stillCached = $this->repository->fetchById($post->id);
        $this->assertEquals('Original Title', $stillCached->title);
    }

    public function test_quiet_update_returns_the_updated_model(): void
    {
        $post = TestPost::create(['title' => 'Before']);

        $updated = $this->repository->update($post->id, ['title' => 'After'], quiet: true);

        $this->assertEquals('After', $updated->title);
    }

    public function test_quiet_update_does_not_fire_model_events(): void
    {
        $post = TestPost::create(['title' => 'Before']);

        $fired = false;
        TestPost::updating(function () use (&$fired): void {
            $fired = true;
        });

        $this->repository->update($post->id, ['title' => 'After'], quiet: true);

        $this->assertFalse($fired);

        TestPost::flushEventListeners();
    }

    public function test_a_normal_update_still_flushes_cache_by_default(): void
    {
        $post = TestPost::create(['title' => 'Original Title']);

        $cached = $this->repository->fetchById($post->id);
        $this->assertEquals('Original Title', $cached->title);

        $this->repository->update($post->id, ['title' => 'Changed Loudly']);

        $fresh = $this->repository->fetchById($post->id);
        $this->assertEquals('Changed Loudly', $fresh->title);
    }

    public function test_it_can_count_records_matching_criteria(): void
    {
        TestPost::create(['title' => 'Post 1', 'status' => 'active']);
        TestPost::create(['title' => 'Post 2', 'status' => 'active']);
        TestPost::create(['title' => 'Post 3', 'status' => 'pending']);

        $this->assertSame(2, $this->repository->count(['filters' => ['__eq_status' => 'active']]));
        $this->assertSame(3, $this->repository->count());
    }

    public function test_it_can_check_existence_of_records_matching_criteria(): void
    {
        TestPost::create(['title' => 'Post 1', 'status' => 'active']);

        $this->assertTrue($this->repository->exists(['filters' => ['__eq_status' => 'active']]));
        $this->assertFalse($this->repository->exists(['filters' => ['__eq_status' => 'pending']]));
    }
}
