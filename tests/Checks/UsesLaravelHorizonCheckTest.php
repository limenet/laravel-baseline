<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesLaravelHorizonCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesLaravelHorizon fails when package is missing or post-deploy script is missing', function (): void {
    // FAIL when package missing
    bindFakeComposer(['laravel/horizon' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(UsesLaravelHorizonCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // FAIL if present but no ci-deploy-post horizon:terminate
    bindFakeComposer(['laravel/horizon' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(UsesLaravelHorizonCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // PASS when script exists
    bindFakeComposer(['laravel/horizon' => true]);
    $composer = ['scripts' => ['ci-deploy-post' => ['php artisan horizon:terminate']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(UsesLaravelHorizonCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
