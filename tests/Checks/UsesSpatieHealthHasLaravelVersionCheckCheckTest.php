<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieHealthHasLaravelVersionCheckCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesSpatieHealthHasLaravelVersionCheck warns when package is not installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesSpatieHealthHasLaravelVersionCheckCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesSpatieHealthHasLaravelVersionCheck fails when AppServiceProvider does not exist', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthHasLaravelVersionCheckCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Health checks not registered: Add Health::checks([LaravelVersionCheck]) in AppServiceProvider');
});

it('usesSpatieHealthHasLaravelVersionCheck fails when LaravelVersionCheck is missing', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $providerWithoutLaravelVersion = <<<'PHP'
<?php
Health::checks([
    CacheCheck::new(),
    PhpVersionCheck::new(),
]);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $providerWithoutLaravelVersion,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthHasLaravelVersionCheckCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Health checks not registered: Add Health::checks([LaravelVersionCheck]) in AppServiceProvider');
});

it('usesSpatieHealthHasLaravelVersionCheck passes when LaravelVersionCheck is registered', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $provider = <<<'PHP'
<?php
Health::checks([
    LaravelVersionCheck::new(),
]);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    expect(makeCheck(UsesSpatieHealthHasLaravelVersionCheckCheck::class)->check())->toBe(CheckResult::PASS);
});
