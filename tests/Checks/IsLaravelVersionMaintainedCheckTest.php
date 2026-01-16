<?php

use Limenet\LaravelBaseline\Checks\Checks\IsLaravelVersionMaintainedCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('isLaravelVersionMaintained passes for Laravel >= 11', function (): void {
    // The dev setup for this package targets Laravel 11/12.
    bindFakeComposer([]);

    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(IsLaravelVersionMaintainedCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
