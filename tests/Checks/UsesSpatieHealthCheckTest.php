<?php

use Illuminate\Support\Facades\Schedule;
use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieHealthCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesSpatieHealth requires scheduled health tasks', function (): void {
    // WARN when not installed
    bindFakeComposer(['spatie/laravel-health' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesSpatieHealthCheck::class);
    expect($check->check())->toBe(CheckResult::WARN);

    // FAIL when installed but not scheduled
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesSpatieHealthCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // PASS when scheduled
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    Schedule::command('health:check');
    Schedule::command('health:schedule-check-heartbeat');
    $check = makeCheck(UsesSpatieHealthCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
