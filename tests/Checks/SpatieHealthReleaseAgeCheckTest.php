<?php

use Limenet\LaravelBaseline\Checks\Checks\SpatieHealthReleaseAgeCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('spatieHealthReleaseAge warns when package is not installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(SpatieHealthReleaseAgeCheck::class)->check())->toBe(CheckResult::WARN);
});

it('spatieHealthReleaseAge fails when composer.json is newer than 6 weeks', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    // Set mtime to 10 days ago
    $composerFile = base_path('composer.json');
    touch($composerFile, time() - (10 * 86400));

    [$check, $collector] = makeCheckWithCollector(SpatieHealthReleaseAgeCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Release is too recent: composer.json was last modified 10 days ago (must be at least 6 weeks old)');
});

it('spatieHealthReleaseAge warns when composer.json is between 6 weeks and 3 months old', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    // Set mtime to 50 days ago (between 42 and 84 days)
    $composerFile = base_path('composer.json');
    touch($composerFile, time() - (50 * 86400));

    [$check, $collector] = makeCheckWithCollector(SpatieHealthReleaseAgeCheck::class);
    expect($check->check())->toBe(CheckResult::WARN);
    expect($collector->all())->toContain('Release is recent: composer.json was last modified 50 days ago (OK at 6 weeks, good at 3 months)');
});

it('spatieHealthReleaseAge passes when composer.json is at least 3 months old', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    // Set mtime to 90 days ago
    $composerFile = base_path('composer.json');
    touch($composerFile, time() - (90 * 86400));

    expect(makeCheck(SpatieHealthReleaseAgeCheck::class)->check())->toBe(CheckResult::PASS);
});
