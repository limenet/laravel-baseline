<?php

use Limenet\LaravelBaseline\Checks\Checks\CallsPeriodicBaselineCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('callsPeriodicBaseline fails when post-update script is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    expect(makeCheck(CallsPeriodicBaselineCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('callsPeriodicBaseline passes when post-update script is present', function (): void {
    bindFakeComposer([]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan limenet:laravel-baseline:periodic']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(CallsPeriodicBaselineCheck::class)->check())->toBe(CheckResult::PASS);
});
