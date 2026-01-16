<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotUseIgnitionCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('doesNotUseIgnition passes only when ignition is not installed', function (): void {
    bindFakeComposer(['spatie/laravel-ignition' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(DoesNotUseIgnitionCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);

    bindFakeComposer(['spatie/laravel-ignition' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(DoesNotUseIgnitionCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});
