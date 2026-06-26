<?php

namespace CoreFoundation\Tests\Unit\Repositories\Cache;

use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Tests\Stubs\Models\TestPost;
use CoreFoundation\Repositories\Cache\CacheKeyBuilder;
use CoreFoundation\Repositories\Cache\PrefixCacheScope;
use CoreFoundation\Repositories\Cache\RelationTagResolver;

class RelationTagResolverTest extends PackageTestCase
{
    private RelationTagResolver $resolver;

    protected function setUp(): void
    {
        parent::setUp();
        $this->resolver = new RelationTagResolver(new CacheKeyBuilder);
    }

    public function test_it_returns_no_tags_for_no_relations(): void
    {
        $this->assertSame([], $this->resolver->resolve(new TestPost, []));
    }

    public function test_it_resolves_a_relation_to_its_real_related_table_tag(): void
    {
        $tags = $this->resolver->resolve(new TestPost, ['comments']);

        $this->assertSame(['test_comments:related'], $tags);
    }

    public function test_the_resolved_tag_matches_what_a_write_to_the_related_model_busts(): void
    {
        $keyBuilder = new CacheKeyBuilder;
        $relationTag = $this->resolver->resolve(new TestPost, ['comments'])[0];

        // This is the exact tag RepositoryCache::flushModel()/flushRecord()/flushAll()
        // bust for a TestComment write — they must be identical or invalidation
        // silently never fires.
        $writeTag = $keyBuilder->buildRelatedTag(
            (new TestPost)->comments()->getRelated(),
        );

        $this->assertSame($writeTag, $relationTag);
    }

    public function test_it_tags_every_segment_of_a_nested_relation_path(): void
    {
        $tags = $this->resolver->resolve(new TestPost, ['comments.post']);

        $this->assertContains('test_comments:related', $tags);
        $this->assertContains('test_posts:related', $tags);
    }

    public function test_it_deduplicates_tags_across_multiple_relations(): void
    {
        $tags = $this->resolver->resolve(new TestPost, ['comments', 'comments.post']);

        $this->assertCount(2, $tags);
    }

    public function test_it_scope_prefixes_relation_tags_to_match_the_query_scope(): void
    {
        $scope = new PrefixCacheScope('tenant:1');

        $tags = $this->resolver->resolve(new TestPost, ['comments'], $scope);

        $this->assertSame(['tenant:1:test_comments:related'], $tags);
    }
}
