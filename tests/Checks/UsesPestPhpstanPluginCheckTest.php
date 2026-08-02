<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesPestPhpstanPluginCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesPestPhpstanPlugin warns when pest is below version 5', function (): void {
    bindFakeComposer(['phpstan/phpstan' => true]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['require-dev' => ['pestphp/pest' => '^4.0']]),
    ]);

    expect(makeCheck(UsesPestPhpstanPluginCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesPestPhpstanPlugin warns when pest is not installed at all', function (): void {
    bindFakeComposer(['phpstan/phpstan' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesPestPhpstanPluginCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesPestPhpstanPlugin warns when phpstan is not installed', function (): void {
    bindFakeComposer(['phpstan/phpstan' => false]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['require-dev' => ['pestphp/pest' => '^5.0']]),
    ]);

    expect(makeCheck(UsesPestPhpstanPluginCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesPestPhpstanPlugin fails when applicable but plugin is missing', function (): void {
    bindFakeComposer(['phpstan/phpstan' => true, 'pestphp/pest-plugin-phpstan' => false]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['require-dev' => ['pestphp/pest' => '^5.0']]),
    ]);

    expect(makeCheck(UsesPestPhpstanPluginCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesPestPhpstanPlugin passes when applicable and plugin is installed', function (): void {
    bindFakeComposer(['phpstan/phpstan' => true, 'pestphp/pest-plugin-phpstan' => true]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['require-dev' => ['pestphp/pest' => '^5.0']]),
    ]);

    expect(makeCheck(UsesPestPhpstanPluginCheck::class)->check())->toBe(CheckResult::PASS);
});
