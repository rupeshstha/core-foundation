<?php

use Illuminate\Support\Facades\Bus;
use CoreFoundation\Jobs\WarmCacheJob;
use CoreFoundation\Repositories\Cache\CacheWarmingRegistry;
use CoreFoundation\Repositories\Cache\Contracts\CacheWarmer;

it('dispatches background jobs for each warmer', function () {
    Bus::fake();

    $registry = new CacheWarmingRegistry;

    $warmerA = mock(CacheWarmer::class);
    $warmerA->shouldReceive('name')->andReturn('warmer-a');

    $warmerB = mock(CacheWarmer::class);
    $warmerB->shouldReceive('name')->andReturn('warmer-b');

    $registry->register($warmerA);
    $registry->register($warmerB);

    $this->app->instance(CacheWarmingRegistry::class, $registry);

    $this->artisan('core:warm-cache')
        ->assertExitCode(0);

    Bus::assertDispatched(WarmCacheJob::class, 2);
});

it('executes warmer logic in the background job', function () {
    $registry = new CacheWarmingRegistry;
    $warmer = mock(CacheWarmer::class);
    $warmer->shouldReceive('name')->andReturn('test-warmer');
    $warmer->shouldReceive('warm')->once()->with(['tenant_id' => 5]);

    $registry->register($warmer);

    $job = new WarmCacheJob('test-warmer', ['tenant_id' => 5]);
    $job->handle($registry);
});
