<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesPhpstanExtensionsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesPhpstanExtensions passes only when both extension packages are installed', function (): void {
    bindFakeComposer(['phpstan/phpstan-deprecation-rules' => true, 'phpstan/phpstan-strict-rules' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesPhpstanExtensionsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    bindFakeComposer(['phpstan/extension-installer' => true, 'phpstan/phpstan-deprecation-rules' => true, 'phpstan/phpstan-strict-rules' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesPhpstanExtensionsCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
