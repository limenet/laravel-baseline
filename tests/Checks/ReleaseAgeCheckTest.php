<?php

use Limenet\LaravelBaseline\Checks\Checks\ReleaseAgeCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('releaseAge warns when package is not installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(ReleaseAgeCheck::class)->check())->toBe(CheckResult::WARN);
});

it('releaseAge passes when composer.json is newer than 6 weeks', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $composerFile = base_path('composer.json');
    touch($composerFile, time() - (10 * 86400));

    expect(makeCheck(ReleaseAgeCheck::class)->check())->toBe(CheckResult::PASS);
});

it('releaseAge warns when composer.json is between 6 weeks and 3 months old', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $composerFile = base_path('composer.json');
    touch($composerFile, time() - (50 * 86400));

    [$check, $collector] = makeCheckWithCollector(ReleaseAgeCheck::class);
    expect($check->check())->toBe(CheckResult::WARN);
    expect($collector->all())->toContain('Release is getting old: composer.json was last modified 50 days ago (should be updated within 6 weeks)');
});

it('releaseAge fails when composer.json is at least 3 months old', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $composerFile = base_path('composer.json');
    touch($composerFile, time() - (90 * 86400));

    [$check, $collector] = makeCheckWithCollector(ReleaseAgeCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Release is too old: composer.json was last modified 90 days ago (must be updated within 3 months)');
});
