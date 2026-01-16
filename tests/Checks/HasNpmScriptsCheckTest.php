<?php

use Limenet\LaravelBaseline\Checks\Checks\HasNpmScriptsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('hasNpmScripts passes when ci-lint and production scripts exist', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'scripts' => [
            'ci-lint' => 'prettier --check .',
            'production' => 'vite build',
        ],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
    ]);

    $check = makeCheck(HasNpmScriptsCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('hasNpmScripts fails when package.json is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    $check = makeCheck(HasNpmScriptsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('hasNpmScripts fails when ci-lint script is missing', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'scripts' => [
            'production' => 'vite build',
        ],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
    ]);

    $check = makeCheck(HasNpmScriptsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('hasNpmScripts fails when production script is missing', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
        'scripts' => [
            'ci-lint' => 'prettier --check .',
        ],
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
    ]);

    $check = makeCheck(HasNpmScriptsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('hasNpmScripts fails when scripts section is missing', function (): void {
    bindFakeComposer([]);
    $packageJson = [
        'name' => 'test-project',
    ];

    $this->withTempBasePath([
        'package.json' => json_encode($packageJson),
    ]);

    $check = makeCheck(HasNpmScriptsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});
