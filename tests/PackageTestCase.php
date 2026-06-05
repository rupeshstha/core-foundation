<?php

namespace CoreFoundation\Tests;

use Orchestra\Testbench\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use CoreFoundation\Repositories\Filter\FilterApplicator;
use CoreFoundation\Providers\CoreFoundationServiceProvider;

abstract class PackageTestCase extends TestCase
{
    use RefreshDatabase;

    protected function getPackageProviders($app): array
    {
        return [CoreFoundationServiceProvider::class];
    }

    protected function getEnvironmentSetUp($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        $app['config']->set('cache.default', 'array');
        $app['config']->set('repository', require __DIR__.'/../config/repository.php');

        // Package tests run without an application auth layer.
        // Auth guard selection is application-specific — not the package's concern.
        $app['config']->set('core-foundation.auth.features_middleware', []);
    }

    protected function defineDatabaseMigrations(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/database/migrations');
    }

    protected function setUp(): void
    {
        parent::setUp();
        FilterApplicator::reset();
    }
}
