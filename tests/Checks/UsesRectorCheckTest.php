<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesRectorCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesRector fails unless both rector packages installed and ci-lint script configured', function (): void {
    // FAIL when packages not installed
    bindFakeComposer(['rector/rector' => true, 'driftingly/rector-laravel' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesRectorCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // FAIL when packages installed but ci-lint script missing
    bindFakeComposer(['rector/rector' => true, 'driftingly/rector-laravel' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(UsesRectorCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // PASS when packages installed and ci-lint script configured
    bindFakeComposer(['rector/rector' => true, 'driftingly/rector-laravel' => true]);
    $composer = ['scripts' => ['ci-lint' => ['rector']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(UsesRectorCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
