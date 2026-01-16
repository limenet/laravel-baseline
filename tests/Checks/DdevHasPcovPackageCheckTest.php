<?php

use Limenet\LaravelBaseline\Checks\Checks\DdevHasPcovPackageCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('ddevHasPcovPackage passes when all requirements are met', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
webimage_extra_packages: ["php${DDEV_PHP_VERSION}-pcov", "php${DDEV_PHP_VERSION}-bcmath"]
YML;

    $customIni = <<<'INI'
[PHP]
opcache.jit=disable
opcache.jit_buffer_size=0
INI;

    $this->withTempBasePath([
        '.ddev/config.yaml' => $ddevConfig,
        '.ddev/php/90-custom.ini' => $customIni,
    ]);

    $check = makeCheck(DdevHasPcovPackageCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('ddevHasPcovPackage fails when .ddev/config.yaml is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    $check = makeCheck(DdevHasPcovPackageCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('ddevHasPcovPackage fails when webimage_extra_packages is missing', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
YML;

    $this->withTempBasePath(['.ddev/config.yaml' => $ddevConfig]);

    $check = makeCheck(DdevHasPcovPackageCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('ddevHasPcovPackage fails when webimage_extra_packages is not an array', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
webimage_extra_packages: "php${DDEV_PHP_VERSION}-pcov"
YML;

    $this->withTempBasePath(['.ddev/config.yaml' => $ddevConfig]);

    $check = makeCheck(DdevHasPcovPackageCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('ddevHasPcovPackage fails when pcov package is not in the list', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
webimage_extra_packages: ["php${DDEV_PHP_VERSION}-bcmath"]
YML;

    $customIni = <<<'INI'
[PHP]
opcache.jit=disable
INI;

    $this->withTempBasePath([
        '.ddev/config.yaml' => $ddevConfig,
        '.ddev/php/90-custom.ini' => $customIni,
    ]);

    $check = makeCheck(DdevHasPcovPackageCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('ddevHasPcovPackage fails when .ddev/php/90-custom.ini is missing', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
webimage_extra_packages: ["php${DDEV_PHP_VERSION}-pcov"]
YML;

    $this->withTempBasePath(['.ddev/config.yaml' => $ddevConfig]);

    $check = makeCheck(DdevHasPcovPackageCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('ddevHasPcovPackage fails when 90-custom.ini does not start with [PHP]', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
webimage_extra_packages: ["php${DDEV_PHP_VERSION}-pcov"]
YML;

    $customIni = <<<'INI'
opcache.jit=disable
INI;

    $this->withTempBasePath([
        '.ddev/config.yaml' => $ddevConfig,
        '.ddev/php/90-custom.ini' => $customIni,
    ]);

    $check = makeCheck(DdevHasPcovPackageCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('ddevHasPcovPackage fails when 90-custom.ini does not contain opcache.jit=disable', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
webimage_extra_packages: ["php${DDEV_PHP_VERSION}-pcov"]
YML;

    $customIni = <<<'INI'
[PHP]
memory_limit=512M
INI;

    $this->withTempBasePath([
        '.ddev/config.yaml' => $ddevConfig,
        '.ddev/php/90-custom.ini' => $customIni,
    ]);

    $check = makeCheck(DdevHasPcovPackageCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('ddevHasPcovPackage passes with [PHP] and whitespace at start', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php
php_version: "8.2"
webimage_extra_packages: ["php${DDEV_PHP_VERSION}-pcov"]
YML;

    $customIni = <<<'INI'
  [PHP]
opcache.jit=disable
INI;

    $this->withTempBasePath([
        '.ddev/config.yaml' => $ddevConfig,
        '.ddev/php/90-custom.ini' => $customIni,
    ]);

    $check = makeCheck(DdevHasPcovPackageCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
