<?php

use Limenet\LaravelBaseline\Checks\Checks\CallsBaselineCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('callsBaseline checks post-update script', function (): void {
    bindFakeComposer([]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan limenet:laravel-baseline:check']]];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(CallsBaselineCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
