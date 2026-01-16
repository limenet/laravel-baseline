<?php

use Limenet\LaravelBaseline\Checks\Checks\PhpVersionMatchesCiCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('phpVersionMatchesCi passes when composer PHP constraint matches CI PHP_VERSION', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];
    $gitlabCi = <<<'YML'
variables:
  PHP_VERSION: "8.2"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    $check = makeCheck(PhpVersionMatchesCiCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('phpVersionMatchesCi fails when composer PHP constraint does not match CI PHP_VERSION', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];
    $gitlabCi = <<<'YML'
variables:
  PHP_VERSION: "8.3"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    $check = makeCheck(PhpVersionMatchesCiCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesCi fails when composer.json is missing', function (): void {
    bindFakeComposer([]);
    $gitlabCi = <<<'YML'
variables:
  PHP_VERSION: "8.2"
YML;

    $this->withTempBasePath(['.gitlab-ci.yml' => $gitlabCi]);

    $check = makeCheck(PhpVersionMatchesCiCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesCi fails when PHP constraint is missing from composer.json', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => []];
    $gitlabCi = <<<'YML'
variables:
  PHP_VERSION: "8.2"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    $check = makeCheck(PhpVersionMatchesCiCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesCi fails when .gitlab-ci.yml is missing', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(PhpVersionMatchesCiCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesCi fails when PHP_VERSION is missing from .gitlab-ci.yml', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.2']];
    $gitlabCi = <<<'YML'
variables:
  OTHER_VAR: "value"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    $check = makeCheck(PhpVersionMatchesCiCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('phpVersionMatchesCi handles PHP constraint without caret', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '8.2']];
    $gitlabCi = <<<'YML'
variables:
  PHP_VERSION: "8.2"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    $check = makeCheck(PhpVersionMatchesCiCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('phpVersionMatchesCi works with different PHP versions', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.3']];
    $gitlabCi = <<<'YML'
variables:
  PHP_VERSION: "8.3"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    $check = makeCheck(PhpVersionMatchesCiCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('phpVersionMatchesCi fails when PHP constraint format is invalid', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '>=7 <9']];
    $gitlabCi = <<<'YML'
variables:
  PHP_VERSION: "8.2"
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        '.gitlab-ci.yml' => $gitlabCi,
    ]);

    $check = makeCheck(PhpVersionMatchesCiCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('PHP version format invalid in composer.json: Use format "^X.Y" (e.g., "^8.4"), found: >=7 <9');
});
