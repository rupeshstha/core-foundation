<?php

namespace CoreFoundation\Tests\Feature\Cache;

use Illuminate\Support\Facades\Cache;
use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Repositories\BaseRepository;
use CoreFoundation\Repositories\Cache\CacheScope;
use CoreFoundation\Repositories\Cache\TenantCacheScope;
use CoreFoundation\Repositories\Cache\CacheDependency;
use CoreFoundation\Repositories\Cache\RepositoryCacheObserver;
use CoreFoundation\Services\BaseService;
use CoreFoundation\Tests\Stubs\Models\TestPost;

class TenantPostRepository extends BaseRepository
{
    public int $tenantId = 1;

    protected function setModel(): string
    {
        return TestPost::class;
    }

    protected function cacheScope(): ?CacheScope
    {
        return new TenantCacheScope($this->tenantId);
    }
}

class PriceCalculationService extends BaseService
{
    public int $callCount = 0;

    public function calculate(TestPost $post, CacheScope $scope)
    {
        return $this->rememberWithDependencies(
            key: "price_calc:{$post->id}",
            dependencies: [CacheDependency::onRecord($post, $post->id, $scope)],
            callback: function() {
                $this->callCount++;
                return "result";
            }
        );
    }
}

class MultiTenantCacheTest extends PackageTestCase
{
    private TenantPostRepository $repository;
    private PriceCalculationService $service;

    protected function getEnvironmentSetUp($app): void
    {
        parent::getEnvironmentSetUp($app);
        $app['config']->set('cache.default', 'array');
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = $this->app->make(TenantPostRepository::class);
        $this->service = $this->app->make(PriceCalculationService::class);

        TestPost::observe(RepositoryCacheObserver::class);
    }

    public function test_surgical_cache_invalidation_across_tenants(): void
    {
        // 1. Setup Data
        $t1_p1 = TestPost::create(['title' => 'T1 P1', 'tenant_id' => 1, 'score' => 100]);
        $t1_p2 = TestPost::create(['title' => 'T1 P2', 'tenant_id' => 1, 'score' => 200]);
        $t2_p1 = TestPost::create(['title' => 'T2 P1', 'tenant_id' => 2, 'score' => 300]);

        $t1_scope = new TenantCacheScope(1);
        $t2_scope = new TenantCacheScope(2);

        // 2. Prime Service Cache for Tenant 1 Post 1
        $this->service->calculate($t1_p1, $t1_scope);
        $this->assertEquals(1, $this->service->callCount, 'First call should be a MISS (call count 1)');

        // Call again - should be a HIT
        $this->service->calculate($t1_p1, $t1_scope);
        $this->assertEquals(1, $this->service->callCount, 'Second call should be a HIT (call count remains 1)');

        // 3. Act: Update Tenant 1's Post 1
        // This triggers RepositoryCacheObserver -> flushRecord(tenant 1, post 1)
        $t1_p1->update(['score' => 150]);

        // 4. Assert Service Invalidation
        $this->service->calculate($t1_p1, $t1_scope);
        $this->assertEquals(2, $this->service->callCount, 'Third call (after update) should be a MISS (call count 2)');

        // 5. Verify Isolation - Tenant 1 Post 2 should still be cached (if we had a service for it)
        $t1_p2_service = $this->app->make(PriceCalculationService::class);
        $t1_p2_service->calculate($t1_p2, $t1_scope); // miss
        $t1_p2_service->calculate($t1_p2, $t1_scope); // hit
        $this->assertEquals(1, $t1_p2_service->callCount);

        // Update T1 P1 again
        $t1_p1->update(['score' => 175]);

        // T1 P2 should STILL be hit
        $t1_p2_service->calculate($t1_p2, $t1_scope);
        $this->assertEquals(1, $t1_p2_service->callCount, 'Update of P1 should NOT bust P2 cache');

        // 6. Verify Isolation - Tenant 2 should be completely untouched
        $t2_service = $this->app->make(PriceCalculationService::class);
        $t2_service->calculate($t2_p1, $t2_scope); // miss
        $t2_service->calculate($t2_p1, $t2_scope); // hit
        $this->assertEquals(1, $t2_service->callCount);

        // Update T1 P1 yet again
        $t1_p1->update(['score' => 200]);

        // T2 P1 should STILL be hit
        $t2_service->calculate($t2_p1, $t2_scope);
        $this->assertEquals(1, $t2_service->callCount, 'Update of Tenant 1 should NOT bust Tenant 2 cache');
    }
}
