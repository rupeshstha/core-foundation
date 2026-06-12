<?php

namespace CoreFoundation\Tests\Feature\Cache;

use Illuminate\Support\Facades\Cache;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Repositories\BaseRepository;
use CoreFoundation\Repositories\Cache\CacheScope;
use CoreFoundation\Repositories\Cache\PrefixCacheScope;
use CoreFoundation\Tests\Stubs\TestPost;

class ScopedProductRepository extends BaseRepository
{
    public int|string $tenantId = 1;

    protected function setModel(): string
    {
        return TestPost::class;
    }

    protected function cacheScope(): ?CacheScope
    {
        return new PrefixCacheScope("tenant:{$this->tenantId}");
    }
}

it('surgical cache invalidation across tenants', function () {
    $repo = $this->app->make(ScopedProductRepository::class);
    $cache = $this->app->make(\CoreFoundation\Repositories\Cache\RepositoryCache::class);
    $model = $repo->getModel();

    $t1_scope = new PrefixCacheScope("tenant:1");
    $t2_scope = new PrefixCacheScope("tenant:2");

    // We verify that the cache tags generated include the scope prefix
    // By manually checking the key builder output which is used by flushModel
    $keyBuilder = $this->app->make(\CoreFoundation\Repositories\Cache\CacheKeyBuilder::class);
    
    expect($keyBuilder->buildListingTag($model, $t1_scope))->toBe('tenant:1:test_posts:listing');
    expect($keyBuilder->buildListingTag($model, $t2_scope))->toBe('tenant:2:test_posts:listing');
});
