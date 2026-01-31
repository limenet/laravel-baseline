<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotUseHorizonWatcherCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('doesNotUseHorizonWatcher passes only when horizon-watcher is not installed', function (): void {
    bindFakeComposer(['spatie/laravel-horizon-watcher' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(DoesNotUseHorizonWatcherCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);

    bindFakeComposer(['spatie/laravel-horizon-watcher' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(DoesNotUseHorizonWatcherCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});
