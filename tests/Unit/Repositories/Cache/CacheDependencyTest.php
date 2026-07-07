<?php

namespace CoreFoundation\Tests\Unit\Repositories\Cache;

use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Tests\Stubs\Models\TestPost;
use CoreFoundation\Repositories\Cache\CacheDependency;
use CoreFoundation\Repositories\Cache\CacheKeyBuilder;
use CoreFoundation\Repositories\Cache\PrefixCacheScope;

class CacheDependencyTest extends PackageTestCase
{
    public function test_on_listing_matches_the_repositorys_own_listing_tag(): void
    {
        $model = new TestPost;
        $keyBuilder = new CacheKeyBuilder;

        $this->assertSame(
            $keyBuilder->buildListingTag($model),
            CacheDependency::onListing($model),
        );
    }

    public function test_on_record_matches_the_repositorys_own_record_tag(): void
    {
        $model = new TestPost;
        $keyBuilder = new CacheKeyBuilder;

        $this->assertSame(
            $keyBuilder->buildRecordTag($model, 42),
            CacheDependency::onRecord($model, 42),
        );
    }

    public function test_on_records_builds_one_tag_per_id(): void
    {
        $model = new TestPost;

        $tags = CacheDependency::onRecords($model, [1, 2, 3]);

        $this->assertSame([
            'test_posts:record:1',
            'test_posts:record:2',
            'test_posts:record:3',
        ], $tags);
    }

    public function test_tags_are_scope_prefixed_consistently(): void
    {
        $model = new TestPost;
        $scope = new PrefixCacheScope('tenant:1');

        $this->assertSame('tenant:1:test_posts:listing', CacheDependency::onListing($model, $scope));
        $this->assertSame('tenant:1:test_posts:record:5', CacheDependency::onRecord($model, 5, $scope));
    }
}
