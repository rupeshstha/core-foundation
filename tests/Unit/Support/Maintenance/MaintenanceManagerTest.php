<?php

namespace CoreFoundation\Tests\Unit\Support\Maintenance;

use CoreFoundation\Tests\PackageTestCase;
use CoreFoundation\Support\Maintenance\MaintenanceManager;
use CoreFoundation\Support\Maintenance\MaintenanceProvider;
use CoreFoundation\Exceptions\MaintenanceModeException;

class MaintenanceManagerTest extends PackageTestCase
{
    private MaintenanceManager $manager;
    private $provider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->manager = new MaintenanceManager();
        $this->provider = mock(MaintenanceProvider::class);
    }

    public function test_it_does_nothing_if_no_provider_set(): void
    {
        // Should not throw any exception
        $this->manager->check();
        $this->assertTrue(true);
    }

    public function test_it_throws_maintenance_exception_when_down(): void
    {
        $this->provider->shouldReceive('isDown')->with(null)->andReturn(true);
        $this->provider->shouldReceive('data')->with(null)->andReturn([
            'message' => 'Down for maintenance',
            'retry_after' => 60,
            'reason' => 'Testing'
        ]);

        $this->manager->setProvider($this->provider);

        $this->expectException(MaintenanceModeException::class);
        $this->expectExceptionMessage('Down for maintenance');

        try {
            $this->manager->check();
        } catch (MaintenanceModeException $e) {
            $this->assertEquals(60, $e->getRetryAfter());
            $this->assertEquals('Testing', $e->getReason());
            throw $e;
        }
    }

    public function test_it_supports_scoped_maintenance(): void
    {
        $this->provider->shouldReceive('isDown')->with('tenant:1')->andReturn(true);
        $this->provider->shouldReceive('data')->with('tenant:1')->andReturn([
            'message' => 'Tenant 1 is down',
            'retry_after' => null,
            'reason' => null
        ]);

        $this->manager->setProvider($this->provider);
        $this->manager->resolveScopeUsing(fn() => 'tenant:1');

        $this->expectException(MaintenanceModeException::class);
        $this->expectExceptionMessage('Tenant 1 is down');

        $this->manager->check();
    }

    public function test_it_continues_if_not_down(): void
    {
        $this->provider->shouldReceive('isDown')->with('tenant:2')->andReturn(false);
        
        $this->manager->setProvider($this->provider);
        $this->manager->resolveScopeUsing(fn() => 'tenant:2');

        $this->manager->check();
        $this->assertTrue(true);
    }
}
