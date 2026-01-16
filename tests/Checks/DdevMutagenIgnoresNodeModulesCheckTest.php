<?php

use Limenet\LaravelBaseline\Checks\Checks\DdevMutagenIgnoresNodeModulesCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('ddevMutagenIgnoresNodeModules passes when mutagen.yml has /node_modules in ignore paths', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
        - "/.git"
YML;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
    ]);

    $check = makeCheck(DdevMutagenIgnoresNodeModulesCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('ddevMutagenIgnoresNodeModules fails when mutagen.yml is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    $check = makeCheck(DdevMutagenIgnoresNodeModulesCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('ddevMutagenIgnoresNodeModules fails when /node_modules is not in ignore paths', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/.git"
        - "/vendor"
YML;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
    ]);

    $check = makeCheck(DdevMutagenIgnoresNodeModulesCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('ddevMutagenIgnoresNodeModules provides helpful comment when file is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    $check = makeCheck(DdevMutagenIgnoresNodeModulesCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('DDEV Mutagen configuration missing: Create .ddev/mutagen/mutagen.yml');
});

it('ddevMutagenIgnoresNodeModules provides helpful comment when /node_modules is missing', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/.git"
YML;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
    ]);

    $check = makeCheck(DdevMutagenIgnoresNodeModulesCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('DDEV Mutagen configuration incomplete: Add "/node_modules" to sync.defaults.ignore.paths in .ddev/mutagen/mutagen.yml and run "ddev mutagen reset" to apply changes');
});

it('ddevMutagenIgnoresNodeModules fails when mutagen.yml is ignored in .ddev/.gitignore', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $gitignore = <<<'TXT'
# DDEV-generated settings
/.ddev-docker-compose-*.yaml
/db_snapshots
/sequelpro.spf
/import.yaml
/import-db
/.bgswitch
/.dbimageBuild
/monitoring
/postgres
/traefik
/.webimageBuild
/.webimageExtra
/.ddevstarttime
/mutagen/mutagen.yml
TXT;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
        '.ddev/.gitignore' => $gitignore,
    ]);

    $check = makeCheck(DdevMutagenIgnoresNodeModulesCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('DDEV Mutagen configuration is ignored by git: Remove "/mutagen/mutagen.yml" from .ddev/.gitignore to track the configuration');
});

it('ddevMutagenIgnoresNodeModules fails when mutagen.yml is ignored by directory pattern', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $gitignore = <<<'TXT'
/mutagen/
TXT;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
        '.ddev/.gitignore' => $gitignore,
    ]);

    $check = makeCheck(DdevMutagenIgnoresNodeModulesCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('DDEV Mutagen configuration is ignored by git: Remove "/mutagen/mutagen.yml" from .ddev/.gitignore to track the configuration');
});

it('ddevMutagenIgnoresNodeModules passes when mutagen.yml is not ignored', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $gitignore = <<<'TXT'
# DDEV-generated settings
/.ddev-docker-compose-*.yaml
/db_snapshots
TXT;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
        '.ddev/.gitignore' => $gitignore,
    ]);

    $check = makeCheck(DdevMutagenIgnoresNodeModulesCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('ddevMutagenIgnoresNodeModules passes when .ddev/.gitignore does not exist', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
    ]);

    $check = makeCheck(DdevMutagenIgnoresNodeModulesCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('ddevMutagenIgnoresNodeModules fails when mutagen.yml contains #ddev-generated comment', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
#ddev-generated
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
    ]);

    $check = makeCheck(DdevMutagenIgnoresNodeModulesCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('DDEV Mutagen configuration is auto-generated: Remove "#ddev-generated" comment from .ddev/mutagen/mutagen.yml to prevent DDEV from overwriting your changes');
});

it('ddevMutagenIgnoresNodeModules fails when mutagen.yml contains #ddev-generated in middle of file', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    #ddev-generated
    ignore:
      paths:
        - "/node_modules"
YML;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
    ]);

    $check = makeCheck(DdevMutagenIgnoresNodeModulesCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('DDEV Mutagen configuration is auto-generated: Remove "#ddev-generated" comment from .ddev/mutagen/mutagen.yml to prevent DDEV from overwriting your changes');
});

it('ddevMutagenIgnoresNodeModules fails when .ddev/.gitignore contains #ddev-generated comment', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $gitignore = <<<'TXT'
#ddev-generated
/.ddev-docker-compose-*.yaml
/db_snapshots
TXT;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
        '.ddev/.gitignore' => $gitignore,
    ]);

    $check = makeCheck(DdevMutagenIgnoresNodeModulesCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('DDEV .gitignore is auto-generated: Remove "#ddev-generated" comment from .ddev/.gitignore to prevent DDEV from regenerating it');
});

it('ddevMutagenIgnoresNodeModules fails when .ddev/.gitignore contains #ddev-generated in middle', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $gitignore = <<<'TXT'
# DDEV-generated settings
#ddev-generated
/.ddev-docker-compose-*.yaml
/db_snapshots
TXT;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
        '.ddev/.gitignore' => $gitignore,
    ]);

    $check = makeCheck(DdevMutagenIgnoresNodeModulesCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('DDEV .gitignore is auto-generated: Remove "#ddev-generated" comment from .ddev/.gitignore to prevent DDEV from regenerating it');
});

it('ddevMutagenIgnoresNodeModules fails when .ddev/.gitignore ignores itself', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $gitignore = <<<'TXT'
/.ddev-docker-compose-*.yaml
/db_snapshots
/.gitignore
TXT;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
        '.ddev/.gitignore' => $gitignore,
    ]);

    $check = makeCheck(DdevMutagenIgnoresNodeModulesCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('DDEV .gitignore is ignoring itself: Remove "/.gitignore" from .ddev/.gitignore to track the gitignore file');
});

it('ddevMutagenIgnoresNodeModules fails when .ddev/.gitignore ignores itself without slash', function (): void {
    bindFakeComposer([]);
    $mutagenConfig = <<<'YML'
sync:
  defaults:
    ignore:
      paths:
        - "/node_modules"
YML;

    $gitignore = <<<'TXT'
/.ddev-docker-compose-*.yaml
/db_snapshots
.gitignore
TXT;

    $this->withTempBasePath([
        '.ddev/mutagen/mutagen.yml' => $mutagenConfig,
        '.ddev/.gitignore' => $gitignore,
    ]);

    $check = makeCheck(DdevMutagenIgnoresNodeModulesCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('DDEV .gitignore is ignoring itself: Remove "/.gitignore" from .ddev/.gitignore to track the gitignore file');
});
