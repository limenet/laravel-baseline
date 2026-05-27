<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieHealthScheduleCheckHeartbeatCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesSpatieHealthScheduleCheckHeartbeat implements FixableInterface', function (): void {
    expect(makeCheck(UsesSpatieHealthScheduleCheckHeartbeatCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('usesSpatieHealthScheduleCheckHeartbeat fix appends heartbeatMaxAgeInMinutes(2) to ScheduleCheck', function (): void {
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

    $check = makeCheck(UsesSpatieHealthScheduleCheckHeartbeatCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $contents = file_get_contents(base_path('app/Providers/AppServiceProvider.php'));
    expect($contents)->toContain('ScheduleCheck::new()->heartbeatMaxAgeInMinutes(2)');
});

it('usesSpatieHealthScheduleCheckHeartbeat fix preserves an existing chained call', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                ScheduleCheck::new()->useCacheStore('health-checks'),
            ]);
            PHP,
    ]);

    $check = makeCheck(UsesSpatieHealthScheduleCheckHeartbeatCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $contents = file_get_contents(base_path('app/Providers/AppServiceProvider.php'));
    expect($contents)->toContain("ScheduleCheck::new()->useCacheStore('health-checks')->heartbeatMaxAgeInMinutes(2)");
});

it('usesSpatieHealthScheduleCheckHeartbeat fix is idempotent when already correct', function (): void {
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

    $check = makeCheck(UsesSpatieHealthScheduleCheckHeartbeatCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect($check->fix())->toBe(CheckResult::PASS);
});

it('usesSpatieHealthScheduleCheckHeartbeat fix does not duplicate a wrong-value call', function (): void {
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

    $check = makeCheck(UsesSpatieHealthScheduleCheckHeartbeatCheck::class);
    expect($check->fix())->toBe(CheckResult::FAIL);

    $contents = file_get_contents(base_path('app/Providers/AppServiceProvider.php'));
    expect(substr_count($contents, 'heartbeatMaxAgeInMinutes'))->toBe(1);
});

it('usesSpatieHealthScheduleCheckHeartbeat fix fails when no ScheduleCheck is registered', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                CacheCheck::new(),
            ]);
            PHP,
    ]);

    $check = makeCheck(UsesSpatieHealthScheduleCheckHeartbeatCheck::class);
    expect($check->fix())->toBe(CheckResult::FAIL);
});
