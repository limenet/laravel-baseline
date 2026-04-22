<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieHealthHasReleaseAgeCheckCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesSpatieHealthHasReleaseAgeCheck warns when package is not installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesSpatieHealthHasReleaseAgeCheckCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesSpatieHealthHasReleaseAgeCheck fails when AppServiceProvider does not exist', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthHasReleaseAgeCheckCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Health checks not registered: Add Health::checks([ReleaseAgeCheck]) in AppServiceProvider');
});

it('usesSpatieHealthHasReleaseAgeCheck fails when ReleaseAgeCheck is missing', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $providerWithoutReleaseAge = <<<'PHP'
<?php
Health::checks([
    CacheCheck::new(),
    PhpVersionCheck::new(),
]);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $providerWithoutReleaseAge,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthHasReleaseAgeCheckCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Health checks not registered: Add Health::checks([ReleaseAgeCheck]) in AppServiceProvider');
});

it('usesSpatieHealthHasReleaseAgeCheck passes when ReleaseAgeCheck is registered', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $provider = <<<'PHP'
<?php
Health::checks([
    ReleaseAgeCheck::new(),
]);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    expect(makeCheck(UsesSpatieHealthHasReleaseAgeCheckCheck::class)->check())->toBe(CheckResult::PASS);
});
