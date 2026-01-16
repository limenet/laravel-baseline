<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesLimenetPintConfigCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesLimenetPintConfig requires package and post-update publish script', function (): void {
    // FAIL when missing package or script
    bindFakeComposer(['limenet/laravel-pint-config' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(UsesLimenetPintConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // PASS when both present
    bindFakeComposer(['limenet/laravel-pint-config' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan laravel-pint-config:publish']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(UsesLimenetPintConfigCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
