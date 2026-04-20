<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieHealthHasPhpVersionCheckCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesSpatieHealthHasPhpVersionCheck warns when package is not installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesSpatieHealthHasPhpVersionCheckCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesSpatieHealthHasPhpVersionCheck fails when AppServiceProvider does not exist', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthHasPhpVersionCheckCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Health checks not registered: Add Health::checks([PhpVersionCheck]) in AppServiceProvider');
});

it('usesSpatieHealthHasPhpVersionCheck fails when PhpVersionCheck is missing', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $providerWithoutPhpVersion = <<<'PHP'
<?php
Health::checks([
    CacheCheck::new(),
    LaravelVersionCheck::new(),
]);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $providerWithoutPhpVersion,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthHasPhpVersionCheckCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Health checks not registered: Add Health::checks([PhpVersionCheck]) in AppServiceProvider');
});

it('usesSpatieHealthHasPhpVersionCheck passes when PhpVersionCheck is registered', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $provider = <<<'PHP'
<?php
Health::checks([
    PhpVersionCheck::new(),
]);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    expect(makeCheck(UsesSpatieHealthHasPhpVersionCheckCheck::class)->check())->toBe(CheckResult::PASS);
});
