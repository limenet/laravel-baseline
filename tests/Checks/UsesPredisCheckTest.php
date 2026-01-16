<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesPredisCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesPredis fails when not installed and passes when installed', function (): void {
    bindFakeComposer(['predis/predis' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesPredisCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    bindFakeComposer(['predis/predis' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesPredisCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
