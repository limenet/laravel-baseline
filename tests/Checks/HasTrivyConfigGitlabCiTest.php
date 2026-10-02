<?php

use Limenet\LaravelBaseline\Checks\Checks\HasCiJobsCheck;
use Limenet\LaravelBaseline\Checks\Checks\HasTrivyConfigCheck;
use Limenet\LaravelBaseline\Checks\Checks\PhpVersionMatchesCiCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

/**
 * Regression: GitLab's `!reference` tag made .gitlab-ci.yml unparseable, and
 * hasTrivyConfig's fix then treated the file as empty and replaced every job
 * in it with the security job.
 */
const GITLAB_CI_WITH_REFERENCE = <<<'YML'
# Shared templates
include:
  - project: "limenet/ci-cd-templates"
    file: "laravel/lint.yml"

variables:
  PHP_VERSION: "8.4"

build:
  extends:
    - .build

php:
  extends:
    - .lint_php

js:
  extends:
    - .lint_js

test:
  extends:
    - .test_db
  before_script:
    - !reference [.test_db, before_script]
    - php scripts/install-test-wordpress.php

YML;

it('reads a .gitlab-ci.yml that uses !reference', function (): void {
    $project = makeProject(Profile::Php, [
        '.gitlab-ci.yml' => GITLAB_CI_WITH_REFERENCE,
        'composer.json' => json_encode(['require' => ['php' => '^8.4']]),
    ]);

    expect(makeCheck(HasCiJobsCheck::class, $project)->check())->toBe(CheckResult::PASS)
        ->and(makeCheck(PhpVersionMatchesCiCheck::class, $project)->check())->toBe(CheckResult::PASS);
});

it('hasTrivyConfig appends the security job without touching the rest of the file', function (): void {
    $this->withTempBasePath(['.gitlab-ci.yml' => GITLAB_CI_WITH_REFERENCE]);

    makeCheck(HasTrivyConfigCheck::class)->fix();

    expect(file_get_contents(base_path('.gitlab-ci.yml')))
        ->toBe(GITLAB_CI_WITH_REFERENCE."\nsecurity:\n  extends:\n    - .lint_security\n");
});

it('hasTrivyConfig never rewrites a .gitlab-ci.yml it cannot parse', function (): void {
    $broken = "build:\n  extends: [.build\n";
    $this->withTempBasePath(['.gitlab-ci.yml' => $broken]);

    [$check, $collector] = makeCheckWithCollector(HasTrivyConfigCheck::class);

    expect($check->fix())->toBe(CheckResult::FAIL)
        ->and(file_get_contents(base_path('.gitlab-ci.yml')))->toBe($broken)
        ->and(implode("\n", $collector->all()))->toContain('.gitlab-ci.yml could not be parsed');
});

it('phpVersionMatchesCi fails cleanly on a tagged PHP_VERSION', function (): void {
    $project = makeProject(Profile::Php, [
        '.gitlab-ci.yml' => "variables:\n  PHP_VERSION: !reference [.vars, PHP_VERSION]\n",
        'composer.json' => json_encode(['require' => ['php' => '^8.4']]),
    ]);

    expect(makeCheck(PhpVersionMatchesCiCheck::class, $project)->check())->toBe(CheckResult::FAIL);
});
