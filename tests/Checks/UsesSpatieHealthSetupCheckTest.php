<?php

use Illuminate\Support\Facades\Schedule;
use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieHealthSetupCheck;
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
    LaravelVersionCheck::new(),
    PhpVersionCheck::new(),
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
], 'notifications' => ['enabled' => false]];
PHP;

it('usesSpatieHealthSetup fails when packages are not installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => false, 'spatie/cpu-load-health-check' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthSetupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing packages: Install spatie/laravel-health and spatie/cpu-load-health-check');
});

it('usesSpatieHealthSetup fails when only spatie/laravel-health is installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthSetupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing packages: Install spatie/laravel-health and spatie/cpu-load-health-check');
});

it('usesSpatieHealthSetup fails when health:check is not scheduled', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthSetupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing schedule: Add RunHealthChecksCommand::class scheduled everyThirtyMinutes() in your scheduler');
});

it('usesSpatieHealthSetup fails when health:schedule-check-heartbeat is not scheduled', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    Schedule::command('health:check');

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthSetupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing schedule: Add ScheduleCheckHeartbeatCommand::class scheduled everyMinute() in your scheduler');
});

it('usesSpatieHealthSetup fails when s3_health disk is missing from filesystems.php', function () use ($validHealth): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/filesystems.php' => '<?php return ["disks" => ["local" => []]];',
        'config/health.php' => $validHealth,
    ]);

    Schedule::command('health:check');
    Schedule::command('health:schedule-check-heartbeat');

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthSetupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing s3_health disk: Add s3_health disk definition to config/filesystems.php');
});

it('usesSpatieHealthSetup fails when health.php result store is not configured', function () use ($validFilesystems): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/filesystems.php' => $validFilesystems,
        'config/health.php' => '<?php return ["result_stores" => []];',
    ]);

    Schedule::command('health:check');
    Schedule::command('health:schedule-check-heartbeat');

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthSetupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing health result store: Configure JsonFileHealthResultStore with disk s3_health and path health.json in config/health.php, and set notifications.enabled to false');
});

it('usesSpatieHealthSetup fails when notifications are not disabled in health.php', function () use ($validFilesystems): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);

    $healthWithoutNotificationsDisabled = <<<'PHP'
<?php
return ['result_stores' => [
    \Spatie\Health\ResultStores\JsonFileHealthResultStore::class => [
        'disk' => 's3_health',
        'path' => 'health.json',
    ],
]];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/filesystems.php' => $validFilesystems,
        'config/health.php' => $healthWithoutNotificationsDisabled,
    ]);

    Schedule::command('health:check');
    Schedule::command('health:schedule-check-heartbeat');

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthSetupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing health result store: Configure JsonFileHealthResultStore with disk s3_health and path health.json in config/health.php, and set notifications.enabled to false');
});

it('usesSpatieHealthSetup passes when fully configured', function () use ($validFilesystems, $validHealth): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'spatie/cpu-load-health-check' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/filesystems.php' => $validFilesystems,
        'config/health.php' => $validHealth,
    ]);

    Schedule::command('health:check');
    Schedule::command('health:schedule-check-heartbeat');

    expect(makeCheck(UsesSpatieHealthSetupCheck::class)->check())->toBe(CheckResult::PASS);
});
