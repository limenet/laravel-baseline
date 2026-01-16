<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesLaravelPennantCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesLaravelPennant warns when package is missing and fails when post-deploy script is missing', function (): void {
    // WARN when not installed
    bindFakeComposer(['laravel/pennant' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(UsesLaravelPennantCheck::class);
    expect($check->check())->toBe(CheckResult::WARN);

    // FAIL when installed but missing script
    bindFakeComposer(['laravel/pennant' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(UsesLaravelPennantCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // PASS when script exists
    bindFakeComposer(['laravel/pennant' => true]);
    $composer = ['scripts' => ['ci-deploy-post' => ['php artisan pennant:purge']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(UsesLaravelPennantCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
