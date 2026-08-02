<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesPestRectorPluginCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesPestRectorPlugin warns when pest is below version 5', function (): void {
    bindFakeComposer(['rector/rector' => true]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['require-dev' => ['pestphp/pest' => '^4.0']]),
    ]);

    expect(makeCheck(UsesPestRectorPluginCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesPestRectorPlugin warns when pest is not installed at all', function (): void {
    bindFakeComposer(['rector/rector' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesPestRectorPluginCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesPestRectorPlugin warns when rector is not installed', function (): void {
    bindFakeComposer(['rector/rector' => false]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['require-dev' => ['pestphp/pest' => '^5.0']]),
    ]);

    expect(makeCheck(UsesPestRectorPluginCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesPestRectorPlugin fails when applicable but plugin is missing', function (): void {
    bindFakeComposer(['rector/rector' => true, 'pestphp/pest-plugin-rector' => false]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['require-dev' => ['pestphp/pest' => '^5.0']]),
    ]);

    expect(makeCheck(UsesPestRectorPluginCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesPestRectorPlugin passes when applicable and plugin is installed', function (): void {
    bindFakeComposer(['rector/rector' => true, 'pestphp/pest-plugin-rector' => true]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['require-dev' => ['pestphp/pest' => '^5.0']]),
    ]);

    expect(makeCheck(UsesPestRectorPluginCheck::class)->check())->toBe(CheckResult::PASS);
});
