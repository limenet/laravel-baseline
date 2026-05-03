<?php

use Limenet\LaravelBaseline\Checks\Checks\HasTrivyConfigCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('hasTrivyConfig passes when security job and trivy.yaml are properly configured', function (): void {
    bindFakeComposer([]);
    $ciYaml = <<<'YML'
security:
  extends: ['.lint_security']
YML;
    $trivyYaml = <<<'YML'
scan:
  scanners:
    - secret
    - vuln
severity:
  - CRITICAL
  - HIGH
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.gitlab-ci.yml' => $ciYaml,
        'trivy.yaml' => $trivyYaml,
    ]);

    expect(makeCheck(HasTrivyConfigCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hasTrivyConfig fails when .gitlab-ci.yml is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('.gitlab-ci.yml not found');
});

it('hasTrivyConfig fails when security CI job is missing or misconfigured', function (): void {
    bindFakeComposer([]);
    $ciYaml = "build:\n  extends: ['.build']\n";

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.gitlab-ci.yml' => $ciYaml,
    ]);

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing or misconfigured CI job in .gitlab-ci.yml: Add job 'security' with 'extends: [.lint_security]'");
});

it('hasTrivyConfig fails when trivy.yaml is missing', function (): void {
    bindFakeComposer([]);
    $ciYaml = <<<'YML'
security:
  extends: ['.lint_security']
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.gitlab-ci.yml' => $ciYaml,
    ]);

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('trivy.yaml not found');
});

it('hasTrivyConfig fails when trivy.yaml is missing required scanners', function (): void {
    bindFakeComposer([]);
    $ciYaml = <<<'YML'
security:
  extends: ['.lint_security']
YML;
    $trivyYaml = <<<'YML'
scan:
  scanners:
    - secret
severity:
  - CRITICAL
  - HIGH
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.gitlab-ci.yml' => $ciYaml,
        'trivy.yaml' => $trivyYaml,
    ]);

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing required scanners in trivy.yaml: scan.scanners must include 'secret' and 'vuln'");
});

it('hasTrivyConfig fails when trivy.yaml is missing required severity levels', function (): void {
    bindFakeComposer([]);
    $ciYaml = <<<'YML'
security:
  extends: ['.lint_security']
YML;
    $trivyYaml = <<<'YML'
scan:
  scanners:
    - secret
    - vuln
severity:
  - CRITICAL
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.gitlab-ci.yml' => $ciYaml,
        'trivy.yaml' => $trivyYaml,
    ]);

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing required severity levels in trivy.yaml: severity must include 'CRITICAL' and 'HIGH'");
});
