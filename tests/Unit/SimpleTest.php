<?php

use CoreFoundation\Support\Maintenance\MaintenanceManager;
use CoreFoundation\Exceptions\MaintenanceModeException;

it('can instantiate manager', function () {
    $manager = new MaintenanceManager();
    expect($manager)->toBeInstanceOf(MaintenanceManager::class);
});

it('can throw exception', function () {
    expect(fn() => throw new MaintenanceModeException('Down'))->toThrow(MaintenanceModeException::class);
});
