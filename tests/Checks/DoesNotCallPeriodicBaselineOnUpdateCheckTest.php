<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotCallPeriodicBaselineOnUpdateCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('doesNotCallPeriodicBaselineOnUpdate passes when post-update script is absent', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    expect(makeCheck(DoesNotCallPeriodicBaselineOnUpdateCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotCallPeriodicBaselineOnUpdate fails when post-update script is present', function (): void {
    bindFakeComposer([]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan limenet:laravel-baseline:periodic']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(DoesNotCallPeriodicBaselineOnUpdateCheck::class)->check())->toBe(CheckResult::FAIL);
});
