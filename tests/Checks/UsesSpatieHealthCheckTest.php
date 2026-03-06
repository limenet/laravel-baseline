<?php

use Illuminate\Support\Facades\Schedule;
use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieHealthCheck;
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

$validFilesystems = <<<'PHP'
<?php
return ['disks' => ['s3_health' => ['driver' => 's3']]];
PHP;

$validHealth = <<<'PHP'
<?php
return ['result_stores' => [
    \Spatie\Health\ResultStores\JsonFileHealthResultStore::class => [
        'disk' => 's3_health',
        'path' => 'health.json',
    ],
]];
PHP;

it('usesSpatieHealth warns when packages are not installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => false, 'spatie/cpu-load-health-check' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesSpatieHealthCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesSpatieHealth warns when only spatie/laravel-health is installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesSpatieHealthCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesSpatieHealth fails when health:check is not scheduled', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing schedule: Add RunHealthChecksCommand::class scheduled everyThirtyMinutes() in your scheduler');
});

it('usesSpatieHealth fails when health:schedule-check-heartbeat is not scheduled', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    Schedule::command('health:check');

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing schedule: Add ScheduleCheckHeartbeatCommand::class scheduled everyMinute() in your scheduler');
});

it('usesSpatieHealth fails when health checks are not registered in AppServiceProvider', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    Schedule::command('health:check');
    Schedule::command('health:schedule-check-heartbeat');

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Health checks not registered: Add Health::checks([CacheCheck, CpuLoadCheck, DatabaseCheck, DebugModeCheck, EnvironmentCheck, HorizonCheck, RedisCheck, ScheduleCheck, UsedDiskSpaceCheck]) in AppServiceProvider');
});

it('usesSpatieHealth fails when a required health check class is missing from AppServiceProvider', function () use ($validFilesystems, $validHealth): void {
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
        'config/filesystems.php' => $validFilesystems,
        'config/health.php' => $validHealth,
    ]);

    Schedule::command('health:check');
    Schedule::command('health:schedule-check-heartbeat');

    expect(makeCheck(UsesSpatieHealthCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieHealth fails when s3_health disk is missing from filesystems.php', function () use ($validAppServiceProvider, $validHealth): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/filesystems.php' => '<?php return ["disks" => ["local" => []]];',
        'config/health.php' => $validHealth,
    ]);

    Schedule::command('health:check');
    Schedule::command('health:schedule-check-heartbeat');

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing s3_health disk: Add s3_health disk definition to config/filesystems.php');
});

it('usesSpatieHealth fails when health.php result store is not configured', function () use ($validAppServiceProvider, $validFilesystems): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/filesystems.php' => $validFilesystems,
        'config/health.php' => '<?php return ["result_stores" => []];',
    ]);

    Schedule::command('health:check');
    Schedule::command('health:schedule-check-heartbeat');

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing health result store: Configure JsonFileHealthResultStore with disk s3_health and path health.json in config/health.php');
});

it('usesSpatieHealth passes when fully configured', function () use ($validAppServiceProvider, $validFilesystems, $validHealth): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/filesystems.php' => $validFilesystems,
        'config/health.php' => $validHealth,
    ]);

    Schedule::command('health:check');
    Schedule::command('health:schedule-check-heartbeat');

    expect(makeCheck(UsesSpatieHealthCheck::class)->check())->toBe(CheckResult::PASS);
});
