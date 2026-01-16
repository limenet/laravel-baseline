<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesLarastanCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesLarastan passes only when larastan is installed', function (): void {
    bindFakeComposer(['larastan/larastan' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesLarastanCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    bindFakeComposer(['larastan/larastan' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesLarastanCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
