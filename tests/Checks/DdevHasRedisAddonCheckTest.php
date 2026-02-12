<?php

use Limenet\LaravelBaseline\Checks\Checks\DdevHasRedisAddonCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('ddevHasRedisAddon passes when addon is installed with sufficient version', function (): void {
    bindFakeComposer([]);
    $manifest = <<<'YML'
name: redis
repository: ddev/ddev-redis
version: v2.2.0
install_date: "2026-02-12T21:12:13+01:00"
YML;

    $this->withTempBasePath([
        '.ddev/addon-metadata/redis/manifest.yaml' => $manifest,
    ]);

    $check = makeCheck(DdevHasRedisAddonCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('ddevHasRedisAddon passes with version higher than minimum', function (): void {
    bindFakeComposer([]);
    $manifest = <<<'YML'
name: redis
repository: ddev/ddev-redis
version: v3.0.0
YML;

    $this->withTempBasePath([
        '.ddev/addon-metadata/redis/manifest.yaml' => $manifest,
    ]);

    $check = makeCheck(DdevHasRedisAddonCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('ddevHasRedisAddon fails when manifest file is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    $check = makeCheck(DdevHasRedisAddonCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('ddevHasRedisAddon fails when version is too old', function (): void {
    bindFakeComposer([]);
    $manifest = <<<'YML'
name: redis
repository: ddev/ddev-redis
version: v2.1.0
YML;

    $this->withTempBasePath([
        '.ddev/addon-metadata/redis/manifest.yaml' => $manifest,
    ]);

    $check = makeCheck(DdevHasRedisAddonCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('ddevHasRedisAddon fails when version field is missing', function (): void {
    bindFakeComposer([]);
    $manifest = <<<'YML'
name: redis
repository: ddev/ddev-redis
YML;

    $this->withTempBasePath([
        '.ddev/addon-metadata/redis/manifest.yaml' => $manifest,
    ]);

    $check = makeCheck(DdevHasRedisAddonCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('ddevHasRedisAddon fails when manifest is empty or invalid', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([
        '.ddev/addon-metadata/redis/manifest.yaml' => '',
    ]);

    [$check, $collector] = makeCheckWithCollector(DdevHasRedisAddonCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('DDEV Redis addon manifest is empty or invalid: Check .ddev/addon-metadata/redis/manifest.yaml');
});

it('ddevHasRedisAddon provides comment when addon is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    [$check, $collector] = makeCheckWithCollector(DdevHasRedisAddonCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('DDEV Redis addon not installed: Install with "ddev add-on get ddev/ddev-redis"');
});

it('ddevHasRedisAddon provides comment when version is too old', function (): void {
    bindFakeComposer([]);
    $manifest = <<<'YML'
name: redis
repository: ddev/ddev-redis
version: v1.0.0
YML;

    $this->withTempBasePath([
        '.ddev/addon-metadata/redis/manifest.yaml' => $manifest,
    ]);

    [$check, $collector] = makeCheckWithCollector(DdevHasRedisAddonCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('DDEV Redis addon version too old: Found v1.0.0, requires at least v2.2.0. Update with "ddev add-on get ddev/ddev-redis"');
});
