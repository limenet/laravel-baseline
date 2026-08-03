<?php

use Limenet\LaravelBaseline\Checks\Checks\DdevHasPcovPackageCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Yaml\Yaml;

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

it('ddevHasPcovPackage fix appends to a flow-style list and preserves comments', function (): void {
    bindFakeComposer([]);
    // Mirrors DDEV's real layout: live keys grouped at the top with no
    // interleaved comments, followed by a large commented-out
    // documentation block (which includes an example of the same key).
    $ddevConfig = <<<'YML'
name: test-project
type: php
webimage_extra_packages: ["php${DDEV_PHP_VERSION}-bcmath"]

# Key features of DDEV's config.yaml:

# webimage_extra_packages: [php7.4-tidy, php-bcmath]
# Extra Debian packages that are needed in the webimage can be added here
YML;

    $this->withTempBasePath(['.ddev/config.yaml' => $ddevConfig]);

    $check = makeCheck(DdevHasPcovPackageCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $raw = file_get_contents(base_path('.ddev/config.yaml'));
    expect($raw)->toContain("# Key features of DDEV's config.yaml:");
    expect($raw)->toContain('# webimage_extra_packages: [php7.4-tidy, php-bcmath]');
    expect($raw)->toContain('# Extra Debian packages that are needed in the webimage can be added here');
    expect(substr_count($raw, "\nwebimage_extra_packages:"))->toBe(1);

    $ddev = Yaml::parseFile(base_path('.ddev/config.yaml'));
    expect($ddev['webimage_extra_packages'])->toBe([
        'php${DDEV_PHP_VERSION}-bcmath',
        'php${DDEV_PHP_VERSION}-pcov',
    ]);
});

it('ddevHasPcovPackage fix appends to a block-style list and preserves comments', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
php_version: "8.2"
webimage_extra_packages:
    - php${DDEV_PHP_VERSION}-bcmath

# Key features of DDEV's config.yaml:

# webimage_extra_packages: [php7.4-tidy, php-bcmath]
# Extra Debian packages that are needed in the webimage can be added here
YML;

    $this->withTempBasePath(['.ddev/config.yaml' => $ddevConfig]);

    $check = makeCheck(DdevHasPcovPackageCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $raw = file_get_contents(base_path('.ddev/config.yaml'));
    expect($raw)->toContain("# Key features of DDEV's config.yaml:");
    expect($raw)->toContain('# webimage_extra_packages: [php7.4-tidy, php-bcmath]');
    expect($raw)->toContain('php_version: "8.2"');

    $ddev = Yaml::parseFile(base_path('.ddev/config.yaml'));
    expect($ddev['webimage_extra_packages'])->toBe([
        'php${DDEV_PHP_VERSION}-bcmath',
        'php${DDEV_PHP_VERSION}-pcov',
    ]);
});

it('ddevHasPcovPackage fix creates webimage_extra_packages when missing and preserves comments', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
name: test-project
type: php

# Key features of DDEV's config.yaml:

# webimage_extra_packages: [php7.4-tidy, php-bcmath]
# Extra Debian packages that are needed in the webimage can be added here
YML;

    $this->withTempBasePath(['.ddev/config.yaml' => $ddevConfig]);

    $check = makeCheck(DdevHasPcovPackageCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $raw = file_get_contents(base_path('.ddev/config.yaml'));
    expect($raw)->toContain("# Key features of DDEV's config.yaml:");
    expect($raw)->toContain('# webimage_extra_packages: [php7.4-tidy, php-bcmath]');

    $ddev = Yaml::parseFile(base_path('.ddev/config.yaml'));
    expect($ddev['webimage_extra_packages'])->toBe(['php${DDEV_PHP_VERSION}-pcov']);
});

it('ddevHasPcovPackage fix does not duplicate an already-present pcov package', function (): void {
    bindFakeComposer([]);
    $ddevConfig = <<<'YML'
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
    expect($check->fix())->toBe(CheckResult::PASS);

    $raw = file_get_contents(base_path('.ddev/config.yaml'));
    expect($raw)->toBe($ddevConfig);
});
