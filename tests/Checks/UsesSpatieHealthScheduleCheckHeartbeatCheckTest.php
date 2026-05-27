<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieHealthScheduleCheckHeartbeatCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

$failComment = 'ScheduleCheck not configured correctly: Use ScheduleCheck::new()->heartbeatMaxAgeInMinutes(2) in Health::checks() in AppServiceProvider to prevent false positives';

it('usesSpatieHealthScheduleCheckHeartbeat warns when package is not installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesSpatieHealthScheduleCheckHeartbeatCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesSpatieHealthScheduleCheckHeartbeat fails when AppServiceProvider is missing', function () use ($failComment): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthScheduleCheckHeartbeatCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain($failComment);
});

it('usesSpatieHealthScheduleCheckHeartbeat fails when ScheduleCheck has no heartbeatMaxAgeInMinutes call', function () use ($failComment): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                ScheduleCheck::new(),
            ]);
            PHP,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthScheduleCheckHeartbeatCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain($failComment);
});

it('usesSpatieHealthScheduleCheckHeartbeat fails when heartbeatMaxAgeInMinutes has wrong value', function () use ($failComment): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                ScheduleCheck::new()->heartbeatMaxAgeInMinutes(5),
            ]);
            PHP,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthScheduleCheckHeartbeatCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain($failComment);
});

it('usesSpatieHealthScheduleCheckHeartbeat passes when ScheduleCheck has heartbeatMaxAgeInMinutes(2)', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                ScheduleCheck::new()->heartbeatMaxAgeInMinutes(2),
            ]);
            PHP,
    ]);

    expect(makeCheck(UsesSpatieHealthScheduleCheckHeartbeatCheck::class)->check())->toBe(CheckResult::PASS);
});

it('usesSpatieHealthScheduleCheckHeartbeat passes when heartbeatMaxAgeInMinutes(2) is in a longer chain', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                ScheduleCheck::new()->useCacheStore('health-checks')->heartbeatMaxAgeInMinutes(2),
            ]);
            PHP,
    ]);

    expect(makeCheck(UsesSpatieHealthScheduleCheckHeartbeatCheck::class)->check())->toBe(CheckResult::PASS);
});
