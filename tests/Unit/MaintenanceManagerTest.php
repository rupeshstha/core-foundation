<?php

use CoreFoundation\Exceptions\MaintenanceModeException;
use CoreFoundation\Support\Maintenance\MaintenanceManager;
use CoreFoundation\Support\Maintenance\MaintenanceProvider;

it('does nothing if no provider set', function () {
    $manager = new MaintenanceManager;
    $manager->check();
    expect(true)->toBeTrue();
});

it('throws maintenance exception when down', function () {
    $manager = new MaintenanceManager;
    $provider = mock(MaintenanceProvider::class);

    $provider->shouldReceive('isDown')->with(null)->andReturn(true);
    $provider->shouldReceive('data')->with(null)->andReturn([
        'message' => 'Down for maintenance',
        'retry_after' => 60,
        'reason' => 'Testing',
    ]);

    $manager->setProvider($provider);

    expect(fn () => $manager->check())->toThrow(MaintenanceModeException::class, 'Down for maintenance');
});

it('supports scoped maintenance', function () {
    $manager = new MaintenanceManager;
    $provider = mock(MaintenanceProvider::class);

    $provider->shouldReceive('isDown')->with('tenant:1')->andReturn(true);
    $provider->shouldReceive('data')->with('tenant:1')->andReturn([
        'message' => 'Tenant 1 is down',
        'retry_after' => null,
        'reason' => null,
    ]);

    $manager->setProvider($provider);
    $manager->resolveScopeUsing(fn () => 'tenant:1');

    expect(fn () => $manager->check())->toThrow(MaintenanceModeException::class, 'Tenant 1 is down');
});
