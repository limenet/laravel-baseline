<?php

use Limenet\LaravelBaseline\Checks\Checks\HasEncryptedEnvFileCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('hasEncryptedEnvFile detects encrypted env files in base path', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath(['.env.prod.encrypted' => 'dummy']);

    $check = makeCheck(HasEncryptedEnvFileCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
