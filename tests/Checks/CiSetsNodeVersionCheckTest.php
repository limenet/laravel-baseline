<?php

use Limenet\LaravelBaseline\Checks\Checks\CiSetsNodeVersionCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Yaml\Yaml;

it('ciSetsNodeVersion implements FixableInterface', function (): void {
    expect(makeCheck(CiSetsNodeVersionCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('ciSetsNodeVersion passes when the variable is set to latest', function (): void {
    $this->withTempBasePath([
        '.gitlab-ci.yml' => "variables:\n  NODE_VERSION: latest\n",
    ]);

    expect(makeCheck(CiSetsNodeVersionCheck::class)->check())->toBe(CheckResult::PASS);
});

it('ciSetsNodeVersion fails when .gitlab-ci.yml is missing', function (): void {
    $this->withTempBasePath([]);

    expect(makeCheck(CiSetsNodeVersionCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('ciSetsNodeVersion fails when the variable is absent', function (): void {
    $this->withTempBasePath([
        '.gitlab-ci.yml' => "js:\n  extends:\n    - .lint_js\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(CiSetsNodeVersionCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('.gitlab-ci.yml must set variables.NODE_VERSION to "latest" so the shared CI template resolves the Node version');
});

it('ciSetsNodeVersion fails when the variable pins a specific major', function (): void {
    $this->withTempBasePath([
        '.gitlab-ci.yml' => "variables:\n  NODE_VERSION: \"24\"\n",
    ]);

    expect(makeCheck(CiSetsNodeVersionCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('ciSetsNodeVersion fix creates the variables mapping when absent', function (): void {
    $this->withTempBasePath([
        '.gitlab-ci.yml' => "js:\n  extends:\n    - .lint_js\n",
    ]);

    expect(makeCheck(CiSetsNodeVersionCheck::class)->fix())->toBe(CheckResult::PASS);

    $ci = Yaml::parseFile(base_path('.gitlab-ci.yml'));
    expect($ci['variables']['NODE_VERSION'])->toBe('latest');
    expect($ci['js']['extends'])->toBe(['.lint_js']);
});

it('ciSetsNodeVersion fix adds the variable to an existing mapping', function (): void {
    $this->withTempBasePath([
        '.gitlab-ci.yml' => "variables:\n  NPM_CONFIG_CACHE: .npm\n\njs:\n  extends:\n    - .lint_js\n",
    ]);

    expect(makeCheck(CiSetsNodeVersionCheck::class)->fix())->toBe(CheckResult::PASS);

    $ci = Yaml::parseFile(base_path('.gitlab-ci.yml'));
    expect($ci['variables']['NPM_CONFIG_CACHE'])->toBe('.npm');
    expect($ci['variables']['NODE_VERSION'])->toBe('latest');
});

it('ciSetsNodeVersion fix replaces a pinned major in place', function (): void {
    $this->withTempBasePath([
        '.gitlab-ci.yml' => "variables:\n  NODE_VERSION: \"24\"\n  NPM_CONFIG_CACHE: .npm\n",
    ]);

    expect(makeCheck(CiSetsNodeVersionCheck::class)->fix())->toBe(CheckResult::PASS);

    $raw = file_get_contents(base_path('.gitlab-ci.yml'));
    expect(substr_count($raw, 'NODE_VERSION'))->toBe(1);

    $ci = Yaml::parseFile(base_path('.gitlab-ci.yml'));
    expect($ci['variables']['NODE_VERSION'])->toBe('latest');
    expect($ci['variables']['NPM_CONFIG_CACHE'])->toBe('.npm');
});

it('ciSetsNodeVersion fix preserves comments and unrelated jobs', function (): void {
    $ci = <<<'YML'
# Pipeline for the app
include:
  - project: limenet/ci-templates
    file: /templates.yml

variables:
  # Cache the npm store between jobs
  NPM_CONFIG_CACHE: .npm

php:
  extends:
    - .lint_php
YML;

    $this->withTempBasePath(['.gitlab-ci.yml' => $ci]);

    expect(makeCheck(CiSetsNodeVersionCheck::class)->fix())->toBe(CheckResult::PASS);

    $raw = file_get_contents(base_path('.gitlab-ci.yml'));
    expect($raw)->toContain('# Pipeline for the app');
    expect($raw)->toContain('# Cache the npm store between jobs');
    expect($raw)->toContain('limenet/ci-templates');

    $parsed = Yaml::parseFile(base_path('.gitlab-ci.yml'));
    expect($parsed['variables']['NODE_VERSION'])->toBe('latest');
    expect($parsed['variables']['NPM_CONFIG_CACHE'])->toBe('.npm');
    expect($parsed['php']['extends'])->toBe(['.lint_php']);
});
