<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieHealthHasCoreChecksCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

$validAppServiceProvider = <<<'PHP'
<?php
Health::checks([
    CacheCheck::new(),
    CpuLoadCheck::new(),
    DatabaseCheck::new(),
    DebugModeCheck::new(),
    EnvironmentCheck::new(),
    HorizonCheck::new(),
    RedisCheck::new(),
    ScheduleCheck::new(),
    UsedDiskSpaceCheck::new(),
]);
PHP;

it('usesSpatieHealthHasCoreChecks warns when packages are not installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => false, 'spatie/cpu-load-health-check' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesSpatieHealthHasCoreChecksCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesSpatieHealthHasCoreChecks fails when health checks are not registered in AppServiceProvider', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthHasCoreChecksCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Health checks not registered: Add Health::checks([CacheCheck, CpuLoadCheck, DatabaseCheck, DebugModeCheck, EnvironmentCheck, HorizonCheck, RedisCheck, ScheduleCheck, UsedDiskSpaceCheck]) in AppServiceProvider');
});

it('usesSpatieHealthHasCoreChecks fails when a required health check class is missing from AppServiceProvider', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);

    $incompleteProvider = <<<'PHP'
<?php
Health::checks([
    CacheCheck::new(),
    DatabaseCheck::new(),
]);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $incompleteProvider,
    ]);

    expect(makeCheck(UsesSpatieHealthHasCoreChecksCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieHealthHasCoreChecks passes when EnvironmentCheck uses a ternary expression', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);

    $providerWithTernaryEnvironmentCheck = <<<'PHP'
<?php
Health::checks([
    CacheCheck::new(),
    CpuLoadCheck::new(),
    DatabaseCheck::new(),
    DebugModeCheck::new(),
    $this->app->environment('staging')
        ? EnvironmentCheck::new()->expectEnvironment('staging')
        : EnvironmentCheck::new(),
    HorizonCheck::new(),
    RedisCheck::new(),
    ScheduleCheck::new(),
    UsedDiskSpaceCheck::new(),
]);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $providerWithTernaryEnvironmentCheck,
    ]);

    expect(makeCheck(UsesSpatieHealthHasCoreChecksCheck::class)->check())->toBe(CheckResult::PASS);
});

it('usesSpatieHealthHasCoreChecks passes when CpuLoadCheck has chained method calls', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);

    $providerWithChainedCpuLoad = <<<'PHP'
<?php
Health::checks([
    CacheCheck::new(),
    CpuLoadCheck::new()
        ->failWhenLoadIsHigherInTheLast5Minutes(2.0)
        ->failWhenLoadIsHigherInTheLast15Minutes(1.5),
    DatabaseCheck::new(),
    DebugModeCheck::new(),
    EnvironmentCheck::new(),
    HorizonCheck::new(),
    RedisCheck::new(),
    ScheduleCheck::new(),
    UsedDiskSpaceCheck::new(),
]);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $providerWithChainedCpuLoad,
    ]);

    expect(makeCheck(UsesSpatieHealthHasCoreChecksCheck::class)->check())->toBe(CheckResult::PASS);
});

it('usesSpatieHealthHasCoreChecks passes when fully configured', function () use ($validAppServiceProvider): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
    ]);

    expect(makeCheck(UsesSpatieHealthHasCoreChecksCheck::class)->check())->toBe(CheckResult::PASS);
});
