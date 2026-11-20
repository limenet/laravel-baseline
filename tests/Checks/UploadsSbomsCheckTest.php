<?php

use Limenet\LaravelBaseline\Checks\Checks\UploadsSbomsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\FilesystemProject;
use Limenet\LaravelBaseline\Project\Profile;
use Symfony\Component\Yaml\Yaml;

function sbomInclude(): string
{
    return <<<'YML'
include:
  - project: "limenet/ci-cd-templates"
    ref: master
    file: "laravel/deploy.yml"
  - project: "limenet/ci-cd-templates"
    ref: master
    file: "laravel/sbom.yml"

stages:
  - build
  - deploy
  - sbom

YML;
}

function sbomAppJobs(): string
{
    return <<<'YML'

sbom:php:
  extends:
    - .sbom_php

sbom:npm:
  extends:
    - .sbom_npm

YML;
}

/** caipirinha's pipeline: one deploy job, one server SBOM in its environment. */
function sbomCompleteCi(): string
{
    return sbomInclude().<<<'YML'
variables:
  PHP_VERSION: "8.5"
  DEPLOY_HOST: "157.90.153.165"

deploy:
  extends:
    - .deploy_default_branch
  environment: production

YML.sbomAppJobs().<<<'YML'

sbom:server:
  extends:
    - .sbom_server
  environment:
    name: production
    action: prepare
  variables:
    SBOM_SERVER_NAME: production

YML;
}

/**
 * @param  array<string,string>  $files
 */
function sbomProject(string $ci, array $files = [], Profile $profile = Profile::Php): FilesystemProject
{
    return makeProject($profile, [
        'composer.json' => '{}',
        'package.json' => '{}',
        '.gitlab-ci.yml' => $ci,
        ...$files,
    ]);
}

it('uploadsSboms passes on a complete pipeline in every PHP profile', function (Profile $profile): void {
    expect(makeCheck(UploadsSbomsCheck::class, sbomProject(sbomCompleteCi(), profile: $profile))->check())->toBe(CheckResult::PASS);
})->with([Profile::Php, Profile::WordPress]);

it('uploadsSboms passes on a complete Laravel pipeline', function (): void {
    $this->withTempBasePath(['composer.json' => '{}', 'package.json' => '{}', '.gitlab-ci.yml' => sbomCompleteCi()]);

    expect(makeCheck(UploadsSbomsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('uploadsSboms fails when .gitlab-ci.yml is missing', function (): void {
    [$check, $collector] = makeCheckWithCollector(UploadsSbomsCheck::class, makeProject(Profile::Php, ['composer.json' => '{}']));

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toContain('.gitlab-ci.yml not found');
});

it('uploadsSboms fails when the include is missing', function (): void {
    $ci = str_replace('file: "laravel/sbom.yml"', 'file: "laravel/test.yml"', sbomCompleteCi());
    [$check, $collector] = makeCheckWithCollector(UploadsSbomsCheck::class, sbomProject($ci));

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toContain("Missing include in .gitlab-ci.yml: include file 'laravel/sbom.yml' from project 'limenet/ci-cd-templates'");
});

it('uploadsSboms accepts the template inside an include file list', function (): void {
    $ci = preg_replace('/include:.*?\n\n/s', "include:\n  - project: limenet/ci-cd-templates\n    file:\n      - laravel/deploy.yml\n      - laravel/sbom.yml\n\n", sbomCompleteCi());

    expect(makeCheck(UploadsSbomsCheck::class, sbomProject($ci))->check())->toBe(CheckResult::PASS);
});

it('uploadsSboms fails when the stage is missing', function (): void {
    [$check, $collector] = makeCheckWithCollector(UploadsSbomsCheck::class, sbomProject(str_replace("  - sbom\n", '', sbomCompleteCi())));

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toContain("Missing stage in .gitlab-ci.yml: add 'sbom' to 'stages'");
});

it('uploadsSboms fails when an app job is missing', function (): void {
    [$check, $collector] = makeCheckWithCollector(UploadsSbomsCheck::class, sbomProject(str_replace("sbom:npm:\n  extends:\n    - .sbom_npm\n", '', sbomCompleteCi())));

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toContain("Missing or misconfigured CI job in .gitlab-ci.yml: Add job 'sbom:npm' with 'extends: [.sbom_npm]'");
});

it('uploadsSboms does not require the npm job without a package.json', function (): void {
    $project = makeProject(Profile::Php, [
        'composer.json' => '{}',
        '.gitlab-ci.yml' => str_replace("sbom:npm:\n  extends:\n    - .sbom_npm\n", '', sbomCompleteCi()),
    ]);

    expect(makeCheck(UploadsSbomsCheck::class, $project)->check())->toBe(CheckResult::PASS);
});

it('uploadsSboms requires no server job without an SSH deploy job', function (): void {
    $ci = sbomInclude()."pages:\n  extends:\n    - .deploy_pages\n".sbomAppJobs();

    expect(makeCheck(UploadsSbomsCheck::class, sbomProject($ci))->check())->toBe(CheckResult::PASS);
});

it('uploadsSboms requires a server job per environment even on a shared host', function (): void {
    // foodstock: both environments use the global DEPLOY_HOST, and differ only
    // in their environment-scoped CI/CD variables.
    $ci = sbomInclude().<<<'YML'
variables:
  DEPLOY_HOST: '46.224.33.130'

deploy_w2:
  extends:
    - .deploy_tagged
  environment: w2

deploy_w3:
  extends:
    - .deploy_tagged
  environment: w3

YML.sbomAppJobs().<<<'YML'

sbom:server_w2:
  extends:
    - .sbom_server
  environment:
    name: w2
    action: prepare
  variables:
    SBOM_SERVER_NAME: w2

YML;
    [$check, $collector] = makeCheckWithCollector(UploadsSbomsCheck::class, sbomProject($ci));

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toBe(["Missing server SBOM in .gitlab-ci.yml: deploy environment 'w3' (job 'deploy_w3') needs a job extending .sbom_server with environment {name: w3, action: prepare} and SBOM_SERVER_NAME set"]);
});

it('uploadsSboms takes the environment from the deploy template and requires the deploy job’s own host', function (): void {
    // logosynthese: `deploy` gets production from .deploy_tagged, and
    // deploy_stage overrides DEPLOY_HOST, which its server job must repeat.
    $ci = sbomInclude().<<<'YML'
variables:
  DEPLOY_HOST: "207.154.226.19"

deploy_stage:
  extends:
    - .deploy_default_branch
  variables:
    DEPLOY_HOST: "46.224.38.140"

deploy:
  extends:
    - .deploy_tagged

YML.sbomAppJobs().<<<'YML'

sbom:server:
  extends:
    - .sbom_server
  environment:
    name: production
    action: prepare
  variables:
    SBOM_SERVER_NAME: production

sbom:server_stage:
  extends:
    - .sbom_server
  environment:
    name: staging
    action: prepare
  variables:
    SBOM_SERVER_NAME: stage

YML;
    [$check, $collector] = makeCheckWithCollector(UploadsSbomsCheck::class, sbomProject($ci));

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toBe(["Misconfigured server SBOM job 'sbom:server_stage' in .gitlab-ci.yml: set DEPLOY_HOST to '46.224.38.140' like deploy job 'deploy_stage'"]);

    $fixed = str_replace("    SBOM_SERVER_NAME: stage\n", "    DEPLOY_HOST: \"46.224.38.140\"\n    SBOM_SERVER_NAME: stage\n", $ci);

    expect(makeCheck(UploadsSbomsCheck::class, sbomProject($fixed))->check())->toBe(CheckResult::PASS);
});

it('uploadsSboms fails when a server job does not run in its environment with the prepare action', function (string $environment): void {
    $ci = str_replace("  environment:\n    name: production\n    action: prepare\n", $environment, sbomCompleteCi());
    [$check, $collector] = makeCheckWithCollector(UploadsSbomsCheck::class, sbomProject($ci));

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toBe(["Misconfigured server SBOM job 'sbom:server' in .gitlab-ci.yml: set environment {name: production, action: prepare} so it gets the environment's CI/CD variables without recording a deployment"]);
})->with([
    'no environment' => [''],
    'plain environment' => ["  environment: production\n"],
    'other action' => ["  environment:\n    name: production\n    action: start\n"],
]);

it('uploadsSboms fails when the server name is invalid', function (): void {
    [$check, $collector] = makeCheckWithCollector(UploadsSbomsCheck::class, sbomProject(str_replace('SBOM_SERVER_NAME: production', 'SBOM_SERVER_NAME: Prod_1', sbomCompleteCi())));

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($collector->all())->toBe(["Misconfigured server SBOM job 'sbom:server' in .gitlab-ci.yml: set SBOM_SERVER_NAME to lowercase letters, digits and dashes"]);
});

it('uploadsSboms matches a deploy job without an environment by host', function (): void {
    $ci = sbomInclude()."deploy:\n  extends:\n    - .deploy_sage_tagged\n".sbomAppJobs()."\nsbom:server:\n  extends:\n    - .sbom_server\n  variables:\n    SBOM_SERVER_NAME: production\n";

    expect(makeCheck(UploadsSbomsCheck::class, sbomProject($ci, profile: Profile::WordPress))->check())->toBe(CheckResult::PASS);
});

it('uploadsSboms fix builds the whole setup and keeps the rest of the file', function (): void {
    $ci = <<<'YML'
include:
  - project: 'limenet/ci-cd-templates'
    ref: master
    file: 'laravel/deploy.yml'

stages:
  - build
  - deploy

variables:
  DEPLOY_HOST: '207.154.226.19'

# Stage environment
deploy_stage:
  extends:
    - .deploy_default_branch
  variables:
    DEPLOY_HOST: '46.224.38.140'

deploy:
  extends:
    - .deploy_tagged

test:
  script:
    - !reference [.setup, script]

YML;
    $project = sbomProject($ci);
    $check = makeCheck(UploadsSbomsCheck::class, $project);

    expect($check->fix())->toBe(CheckResult::PASS)
        ->and(file_get_contents($project->path('.gitlab-ci.yml')))->toBe(<<<'YML'
include:
  - project: 'limenet/ci-cd-templates'
    ref: master
    file: 'laravel/deploy.yml'
  - project: 'limenet/ci-cd-templates'
    ref: master
    file: 'laravel/sbom.yml'

stages:
  - build
  - deploy
  - sbom

variables:
  DEPLOY_HOST: '207.154.226.19'

# Stage environment
deploy_stage:
  extends:
    - .deploy_default_branch
  variables:
    DEPLOY_HOST: '46.224.38.140'

deploy:
  extends:
    - .deploy_tagged

test:
  script:
    - !reference [.setup, script]

sbom:php:
  extends:
    - .sbom_php

sbom:npm:
  extends:
    - .sbom_npm

sbom:server_staging:
  extends:
    - .sbom_server
  environment:
    name: staging
    action: prepare
  variables:
    DEPLOY_HOST: '46.224.38.140'
    SBOM_SERVER_NAME: staging

sbom:server_production:
  extends:
    - .sbom_server
  environment:
    name: production
    action: prepare
  variables:
    SBOM_SERVER_NAME: production

YML);
});

it('uploadsSboms fix adds the environment to a server job named after it instead of a duplicate', function (): void {
    $ci = str_replace("  environment:\n    name: production\n    action: prepare\n", '', sbomCompleteCi());
    $project = sbomProject($ci);

    expect(makeCheck(UploadsSbomsCheck::class, $project)->fix())->toBe(CheckResult::PASS);

    $jobs = array_filter(
        Yaml::parse((string) file_get_contents($project->path('.gitlab-ci.yml'))),
        fn (mixed $job): bool => is_array($job) && ($job['extends'] ?? null) === ['.sbom_server'],
    );

    expect($jobs)->toBe(['sbom:server' => [
        'extends' => ['.sbom_server'],
        'variables' => ['SBOM_SERVER_NAME' => 'production'],
        'environment' => ['name' => 'production', 'action' => 'prepare'],
    ]]);
});

it('uploadsSboms fix declares GitLab’s default stages alongside sbom when there are none', function (): void {
    $ci = preg_replace("/stages:\n(  - .*\n)+/", '', sbomCompleteCi());
    $project = sbomProject($ci);

    expect(makeCheck(UploadsSbomsCheck::class, $project)->fix())->toBe(CheckResult::PASS)
        ->and(Yaml::parse((string) file_get_contents($project->path('.gitlab-ci.yml')))['stages'])->toBe(['build', 'test', 'deploy', 'sbom']);
});

it('uploadsSboms fix refuses an include that is not a list', function (): void {
    $ci = preg_replace('/include:.*?\n\n/s', "include: 'ci/local.yml'\n\n", sbomCompleteCi());
    $project = sbomProject($ci);
    [$check, $collector] = makeCheckWithCollector(UploadsSbomsCheck::class, $project);

    expect($check->fix())->toBe(CheckResult::FAIL)
        ->and(file_get_contents($project->path('.gitlab-ci.yml')))->toBe($ci)
        ->and($collector->all())->toContain("Cannot add the include to .gitlab-ci.yml automatically: rewrite 'include' as a list first");
});

it('uploadsSboms fix leaves an unparseable file untouched', function (): void {
    $project = sbomProject("deploy: [\n");

    expect(makeCheck(UploadsSbomsCheck::class, $project)->fix())->toBe(CheckResult::FAIL)
        ->and(file_get_contents($project->path('.gitlab-ci.yml')))->toBe("deploy: [\n");
});
