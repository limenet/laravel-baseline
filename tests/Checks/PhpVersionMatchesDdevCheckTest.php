<?php

use Limenet\LaravelBaseline\Checks\Checks\PhpVersionMatchesDdevCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('phpVersionMatchesDdev passes when composer PHP constraint matches DDEV php_version', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];
    $ddevConfig = <<<'YML'
name: test-project
type: php
docroot: public
php_version: "8.2"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.ddev/config.yaml' => $ddevConfig,
    ]);

    $check = makeCheck(PhpVersionMatchesDdevCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('phpVersionMatchesDdev fails when composer PHP constraint does not match DDEV php_version', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];
    $ddevConfig = <<<'YML'
name: test-project
type: php
docroot: public
php_version: "8.3"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.ddev/config.yaml' => $ddevConfig,
    ]);

    $check = makeCheck(PhpVersionMatchesDdevCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesDdev fails when composer.json is missing', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
php_version: "8.2"
YML;

    $this->withTempBasePath(['.ddev/config.yaml' => $ddevConfig]);

    $check = makeCheck(PhpVersionMatchesDdevCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesDdev fails when PHP constraint is missing from composer.json', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => []];
    $ddevConfig = <<<'YML'
name: test-project
php_version: "8.2"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.ddev/config.yaml' => $ddevConfig,
    ]);

    $check = makeCheck(PhpVersionMatchesDdevCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesDdev fails when .ddev/config.yaml is missing', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(PhpVersionMatchesDdevCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesDdev fails when php_version is missing from .ddev/config.yaml', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];
    $ddevConfig = <<<'YML'
name: test-project
type: php
docroot: public
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.ddev/config.yaml' => $ddevConfig,
    ]);

    $check = makeCheck(PhpVersionMatchesDdevCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesDdev handles PHP constraint without caret', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '8.2']];
    $ddevConfig = <<<'YML'
name: test-project
php_version: "8.2"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.ddev/config.yaml' => $ddevConfig,
    ]);

    $check = makeCheck(PhpVersionMatchesDdevCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('phpVersionMatchesDdev works with different PHP versions', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.3']];
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.3"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.ddev/config.yaml' => $ddevConfig,
    ]);

    $check = makeCheck(PhpVersionMatchesDdevCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
