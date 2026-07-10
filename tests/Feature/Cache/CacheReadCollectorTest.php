<?php

use CoreFoundation\Repositories\Cache\CacheReadCollector;

it('records tags and returns them', function () {
    $collector = new CacheReadCollector;
    $collector->record(['tag-a', 'tag-b']);

    expect($collector->all())->toBe(['tag-a', 'tag-b']);
});

it('deduplicates tags across multiple record calls', function () {
    $collector = new CacheReadCollector;
    $collector->record(['tag-a', 'tag-b']);
    $collector->record(['tag-b', 'tag-c']);

    expect($collector->all())->toBe(['tag-a', 'tag-b', 'tag-c']);
});

it('reports hasAny as false when empty', function () {
    $collector = new CacheReadCollector;

    expect($collector->hasAny())->toBeFalse();
});

it('reports hasAny as true after recording', function () {
    $collector = new CacheReadCollector;
    $collector->record(['tag-a']);

    expect($collector->hasAny())->toBeTrue();
});

it('caps at 50 tags', function () {
    $collector = new CacheReadCollector;
    $collector->record(array_map(fn ($i) => "tag-{$i}", range(1, 100)));

    expect($collector->all())->toHaveCount(50);
});

it('sets isCapped when the cap is exceeded', function () {
    $collector = new CacheReadCollector;
    $collector->record(array_map(fn ($i) => "tag-{$i}", range(1, 60)));

    expect($collector->isCapped())->toBeTrue();
});

it('does not set isCapped when under the cap', function () {
    $collector = new CacheReadCollector;
    $collector->record(array_map(fn ($i) => "tag-{$i}", range(1, 10)));

    expect($collector->isCapped())->toBeFalse();
});

it('clear resets all state', function () {
    $collector = new CacheReadCollector;
    $collector->record(array_map(fn ($i) => "tag-{$i}", range(1, 60)));

    $collector->clear();

    expect($collector->all())->toBe([]);
    expect($collector->hasAny())->toBeFalse();
    expect($collector->isCapped())->toBeFalse();
});
