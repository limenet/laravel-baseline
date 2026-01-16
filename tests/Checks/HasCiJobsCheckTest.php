<?php

use Limenet\LaravelBaseline\Checks\Checks\HasCiJobsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('hasCiJobs parses gitlab-ci.yml for required jobs', function (): void {
    bindFakeComposer([]);
    $yaml = <<<'YML'
build:
  extends: ['.build']
php:
  extends: ['.lint_php']
js:
  extends: ['.lint_js']
test:
  extends: ['.test']
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.gitlab-ci.yml' => $yaml,
    ]);

    $check = makeCheck(HasCiJobsCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('hasCiJobs allows additional keys like before_script in job definitions', function (): void {
    bindFakeComposer([]);
    $yaml = <<<'YML'
build:
  extends: ['.build']
  before_script:
    - composer install
php:
  extends: ['.lint_php']
  variables:
    PHP_CS_FIXER_IGNORE_ENV: 1
js:
  extends: ['.lint_js']
  before_script:
    - npm install
test:
  extends: ['.test']
  artifacts:
    reports:
      junit: report.xml
YML;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.gitlab-ci.yml' => $yaml,
    ]);

    $check = makeCheck(HasCiJobsCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('hasCiJobs fails when required jobs are missing or not extending the correct templates', function (): void {
    bindFakeComposer([]);
    $yaml = "build:\n  extends: ['.wrong']\n";

    $this->withTempBasePath(['.gitlab-ci.yml' => $yaml, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(HasCiJobsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});
