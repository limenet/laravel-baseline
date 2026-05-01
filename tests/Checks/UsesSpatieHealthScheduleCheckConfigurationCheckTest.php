<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieHealthScheduleCheckConfigurationCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesSpatieHealthScheduleCheckConfiguration warns when package is not installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesSpatieHealthScheduleCheckConfigurationCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesSpatieHealthScheduleCheckConfiguration fails when AppServiceProvider is missing', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthScheduleCheckConfigurationCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('ScheduleCheck not configured correctly: Use ScheduleCheck::new()->heartbeatMaxAgeInMinutes(2) in Health::checks() in AppServiceProvider to prevent false positives');
});

it('usesSpatieHealthScheduleCheckConfiguration fails when ScheduleCheck is not in Health::checks()', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $provider = <<<'PHP'
<?php
Health::checks([
    CacheCheck::new(),
]);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    expect(makeCheck(UsesSpatieHealthScheduleCheckConfigurationCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieHealthScheduleCheckConfiguration fails when ScheduleCheck is missing heartbeatMaxAgeInMinutes', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $provider = <<<'PHP'
<?php
Health::checks([
    ScheduleCheck::new(),
]);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthScheduleCheckConfigurationCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('ScheduleCheck not configured correctly: Use ScheduleCheck::new()->heartbeatMaxAgeInMinutes(2) in Health::checks() in AppServiceProvider to prevent false positives');
});

it('usesSpatieHealthScheduleCheckConfiguration fails when heartbeatMaxAgeInMinutes has wrong value', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $provider = <<<'PHP'
<?php
Health::checks([
    ScheduleCheck::new()->heartbeatMaxAgeInMinutes(5),
]);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    expect(makeCheck(UsesSpatieHealthScheduleCheckConfigurationCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieHealthScheduleCheckConfiguration passes when ScheduleCheck has heartbeatMaxAgeInMinutes(2)', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $provider = <<<'PHP'
<?php
Health::checks([
    ScheduleCheck::new()->heartbeatMaxAgeInMinutes(2),
]);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    expect(makeCheck(UsesSpatieHealthScheduleCheckConfigurationCheck::class)->check())->toBe(CheckResult::PASS);
});

it('usesSpatieHealthScheduleCheckConfiguration passes when heartbeatMaxAgeInMinutes(2) is in a longer chain', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $provider = <<<'PHP'
<?php
Health::checks([
    ScheduleCheck::new()->heartbeatMaxAgeInMinutes(2)->someOtherMethod(),
]);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    expect(makeCheck(UsesSpatieHealthScheduleCheckConfigurationCheck::class)->check())->toBe(CheckResult::PASS);
});
