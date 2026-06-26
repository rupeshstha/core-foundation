<?php

namespace CoreFoundation\Console\Commands;

use Illuminate\Console\Command;
use CoreFoundation\Jobs\WarmCacheJob;
use CoreFoundation\Repositories\Cache\CacheWarmingRegistry;

/**
 * WarmCache
 *
 * Artisan command to trigger cache warming.
 *
 * Dispatches background jobs for each warmer/tenant combination to
 * enable high-scale parallel warming.
 */
class WarmCache extends Command
{
    protected $signature = 'core:warm-cache {--warmer= : Specific warmer to run} {--tenant= : Specific tenant to warm} {--sync : Run synchronously instead of queueing}';

    protected $description = 'Proactively populate the cache with expensive computations (Parallel)';

    public function handle(CacheWarmingRegistry $registry): int
    {
        $warmerName = $this->option('warmer');
        $warmerName = is_string($warmerName) ? $warmerName : null;
        $tenantId = $this->option('tenant');
        $sync = $this->option('sync');

        $warmers = $warmerName
            ? ($registry->get($warmerName) ? [$registry->get($warmerName)] : [])
            : $registry->all();

        if (empty($warmers)) {
            $this->error('No warmers found'.($warmerName ? " with name [{$warmerName}]" : ''));

            return 1;
        }

        foreach ($warmers as $warmer) {
            $this->info("Dispatching warmer: {$warmer->name()}...");

            $job = new WarmCacheJob($warmer->name(), ['tenant_id' => $tenantId]);

            if ($sync) {
                $job->handle($registry);
                $this->info("✓ Finished (sync): {$warmer->name()}");
            } else {
                dispatch($job);
                $this->info("✓ Queued: {$warmer->name()}");
            }
        }

        $this->info('All warming tasks have been processed.');

        return 0;
    }
}
