<?php

use Limenet\LaravelBaseline\Checks\Checks\PhpstanLevelAtLeastEightCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('phpstanLevelAtLeastEight fails when phpstan.neon is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(PhpstanLevelAtLeastEightCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('PHPStan configuration missing: Create phpstan.neon in project root');
});

it('phpstanLevelAtLeastEight fails when level parameter is missing', function (): void {
    bindFakeComposer([]);
    $phpstanConfig = <<<'YAML'
parameters:
    paths:
        - src
YAML;
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'phpstan.neon' => $phpstanConfig,
    ]);

    $check = makeCheck(PhpstanLevelAtLeastEightCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('PHPStan level not configured: Add "level" parameter to phpstan.neon');
});

it('phpstanLevelAtLeastEight fails when level is below 8', function (): void {
    bindFakeComposer([]);
    $phpstanConfig = <<<'YAML'
parameters:
    level: 5
    paths:
        - src
YAML;
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'phpstan.neon' => $phpstanConfig,
    ]);

    $check = makeCheck(PhpstanLevelAtLeastEightCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('PHPStan level must be at least 8: Found level 5 in phpstan.neon (set to 8 or higher)');
});

it('phpstanLevelAtLeastEight passes when level is exactly 8', function (): void {
    bindFakeComposer([]);
    $phpstanConfig = <<<'YAML'
parameters:
    level: 8
    paths:
        - src
YAML;
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'phpstan.neon' => $phpstanConfig,
    ]);

    $check = makeCheck(PhpstanLevelAtLeastEightCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('phpstanLevelAtLeastEight passes when level is above 8', function (): void {
    bindFakeComposer([]);
    $phpstanConfig = <<<'YAML'
parameters:
    level: 9
    paths:
        - src
YAML;
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'phpstan.neon' => $phpstanConfig,
    ]);

    $check = makeCheck(PhpstanLevelAtLeastEightCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('phpstanLevelAtLeastEight passes when level is max', function (): void {
    bindFakeComposer([]);
    $phpstanConfig = <<<'YAML'
parameters:
    level: max
    paths:
        - src
YAML;
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'phpstan.neon' => $phpstanConfig,
    ]);

    $check = makeCheck(PhpstanLevelAtLeastEightCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('phpstanLevelAtLeastEight fails when level is invalid string', function (): void {
    bindFakeComposer([]);
    $phpstanConfig = <<<'YAML'
parameters:
    level: invalid
    paths:
        - src
YAML;
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'phpstan.neon' => $phpstanConfig,
    ]);

    $check = makeCheck(PhpstanLevelAtLeastEightCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('PHPStan level must be a number or "max": Found "invalid" in phpstan.neon');
});
