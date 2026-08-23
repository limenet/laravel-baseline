# Development Guidelines

**Note:** Keep this file up-to-date as patterns evolve. If you add new helper methods, configuration parsers, or change the workflow, update the relevant sections in this document.

## Architecture Overview

This repository ships **two runners** from one policy:

- `limenet/laravel-baseline` (Composer, `src/`) — every check, for Laravel projects.
- `@limenet-ch/baseline` (npm, `js/`) — the portable subset, for JS/TS projects with no PHP and no
  DDEV.

They do **not** share code, and deliberately so: PHP cannot reach into a JS project, and this
baseline's own guideline (`resources/boost/guidelines/core.blade.php`) puts `npm` on the host while
artisan runs inside DDEV, so shelling out across that boundary is not an option. What is shared is
the *policy* (`policy/`) and the *behavioural contract* (`fixtures/`), both read by both runners.
Drift is caught by the fixtures, not prevented by a common implementation.

The codebase uses a **one class per check** architecture:

```
src/
├── Checks/
│   ├── CheckInterface.php           # Contract for all checks
│   ├── AbstractCheck.php            # Base class with helper methods
│   ├── PeriodicCheckInterface.php   # Contract for periodic checks
│   ├── AbstractPeriodicCheck.php    # Base class for periodic checks
│   ├── CheckRegistry.php            # Registry of all check classes
│   ├── CommentCollector.php         # Manages comments for a check run
│   └── Checks/                      # Individual check classes
│       ├── BumpsComposerCheck.php
│       └── ...
├── Commands/
│   ├── LaravelBaselineCommand.php   # CI-safe check runner
│   └── PeriodicCheckCommand.php     # Interactive periodic check runner
├── Policy/
│   └── Policy.php                  # Typed reader for policy/policy.json
└── State/
    └── PeriodicStateManager.php    # Reads/writes config/baseline.php for periodic state

resources/
└── boost/                          # Laravel Boost resources shipped to consumers
    ├── guidelines/core.blade.php   # Always-on AI guideline (dev loop, conventions)
    └── skills/<name>/SKILL.md      # On-demand AI skills (e.g. creating-a-release)

policy/                             # SHARED — read at runtime by both runners
├── policy.json                     # Floors, required keys, allow/deny lists
├── policy.schema.json
└── templates/editorconfig          # Canonical file bodies, verbatim

fixtures/                           # SHARED — executed by both test suites
└── <check-name>/<case>/
    ├── case.json                   # engines, expected verdict, expected fix outcome
    └── project/                    # materialised into a temp project root

js/                                 # The npm runner (@limenet-ch/baseline)
├── src/checks/                     # One class per check, mirroring src/Checks/Checks/
├── src/commands/                   # check, periodic, install-skills
├── skills/<name>/SKILL.md          # JS variants, copied by `baseline install-skills`
└── tests/                          # vitest, incl. the shared-fixture runner
```

`js/src` and `js/dist` sit at the same depth on purpose, so `../../policy` resolves identically in
source and in build output. Do not flatten that.

## Check Size Guidelines

Keep each check **focused on a single concern**. When designing a check, distinguish between:

- **Setup** — installing the package, configuring schedules, adding required config files. These belong together since they're all needed before the feature works.
- **Customization / individual requirements** — specific sub-settings or registered entries that can independently be missing and independently disabled. Split these into their own checks.

A check with 3+ distinct conditions is a signal to review: ask whether any of those conditions are independently disableable or belong to a different concern. Good heuristics:
- If skipping one sub-check but not another makes sense for a project → split them.
- If all sub-checks are required together for the setup to function at all → keep them together.

**Example:** `UsesSpatieHealthSetupCheck` (packages + schedules + filesystem disk + result store config) stays together because none of it makes sense without the others. But `LaravelVersionCheck` and `PhpVersionCheck` being registered in `Health::checks()` are independently disableable → separate checks.

When multiple checks share structural logic (e.g., all parse the same file and run the same kind of visitor), extract a shared abstract base class rather than duplicating the parsing logic.

## When Adding a New Check

> **Start with the `adding-a-check` skill** (`.claude/skills/adding-a-check/SKILL.md`). It settles
> the two things that cannot be inferred from the request — which runner(s) the check targets, and
> whether it auto-fixes — before any code is written. Both decisions change the shape of what you
> write, so guessing means rewriting.

### 1. Create a New Check Class

Create a new class in `src/Checks/Checks/` that extends `AbstractCheck`:

```php
<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class MyNewCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages('vendor/package')) {
            return CheckResult::FAIL;
        }

        if (!$this->hasPostUpdateScript('some:command')) {
            $this->addComment('Missing script in composer.json: Add "php artisan some:command" to post-update-cmd section');
            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
```

Key points:
- Class name should be descriptive and end with `Check` (e.g., `UsesPestCheck`, `HasCompleteRectorConfigurationCheck`)
- The `name()` method is auto-derived from class name (e.g., `UsesPestCheck` → `usesPest`)
- Return one of: `CheckResult::PASS`, `CheckResult::FAIL`, or `CheckResult::WARN`
- Use `$this->addComment($message)` to provide helpful error messages when a check fails
- Error messages should be actionable and specific
- Include the file path that needs to be changed when applicable (e.g., "Missing script in composer.json" or "Add to .env.example")

### 2. Register the Check in CheckRegistry

Add your check class to the array in [src/Checks/CheckRegistry.php](src/Checks/CheckRegistry.php):

```php
use Limenet\LaravelBaseline\Checks\Checks\MyNewCheck;

private static array $checks = [
    // ... existing checks ...
    MyNewCheck::class,
];
```

Keep the array alphabetically sorted for maintainability.

### 3. Write Comprehensive Tests

Create a new test file in `tests/Checks/` named `{YourCheckName}Test.php`:

```
tests/Checks/
├── BumpsComposerCheckTest.php
├── MyNewCheckTest.php          # Your new test file
└── ...
```

**Test helpers available** (from `tests/Helpers.php`):
- `makeCheck(CheckClass::class)` - Create a check instance
- `makeCheckWithCollector(CheckClass::class)` - Returns `[$check, $collector]` tuple for comment verification
- `bindFakeComposer(['package' => true/false])` - Mock composer package checks

**Absence test:** Check fails when package/configuration is missing
```php
it('myNew fails when package is missing', function (): void {
    bindFakeComposer(['vendor/package' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    expect(makeCheck(MyNewCheck::class)->check())->toBe(CheckResult::FAIL);
});
```

**Presence test:** Check passes when properly configured
```php
it('myNew passes when properly configured', function (): void {
    bindFakeComposer(['vendor/package' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan some:command']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(MyNewCheck::class)->check())->toBe(CheckResult::PASS);
});
```

**Testing comments:** Use `makeCheckWithCollector()` to verify error messages
```php
it('myNew provides helpful comment when script is missing', function (): void {
    bindFakeComposer(['vendor/package' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    [$check, $collector] = makeCheckWithCollector(MyNewCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing script in composer.json...');
});
```

### 4. Document in README.md (required — enforced by tests)

Add check documentation to [README.md](README.md) under the appropriate category. The entry must use the exact `name()` of the check (e.g., `**\`usesPest()\`**`). The test in `tests/ReadmeChecksTest.php` enforces that every registered check is documented and will fail if the README is missing any. Forgetting this step will break the test suite.

### 4b. Decide whether the check belongs in the npm runner too

Ask whether the check means anything in a project with no PHP, no composer and no DDEV.

- **No** (composer, artisan, Rector, PHPStan, Spatie Health, DDEV) — stop here. Nothing to do.
- **Yes, identically** — add the TS class under `js/src/checks/`, register it in
  `js/src/checks/registry.ts`, and add a fixture with `"engines": ["php", "js"]`.
- **Yes, but with different data** — put the differing values in `policy/policy.json` under a
  `php` / `js` split (see `ci.requiredJobs`, `ciLint.required`, `claude.allow`), have *both* checks
  read their half, and write one fixture per engine.

**Any constant a check compares against belongs in `policy/`, not in the class.** That is the only
thing keeping the two runners from disagreeing about what the standard actually is. Logic stays in
the classes; values move.

### 5. Run the Full Test Suite

Always run the full test suite after adding a new check:

```bash
composer test
```

Adding a new check to the registry can affect other tests (e.g., tests that count total checks or iterate over all registered checks). Do not rely solely on running tests for the new check.

When **renaming** a check class, the `name()` return value changes automatically (it is derived from the class name). Update the README.md entry to match — the `ReadmeChecksTest` will catch any mismatch. Also rename the fixture directory (it must be the kebab-case of the check name, which the conformance test asserts) and, if the check exists on both sides, the `checkName` in the TS class.

If you touched `policy/`, `fixtures/` or anything under `js/`, run the npm side too — a policy
change can break the *other* runner without touching a single PHP file:

```bash
npm run ci-lint && npm run build && npm test
```

## Available Helper Methods in AbstractCheck

### Composer Checks
- `checkComposerPackages(string|array $packages): bool` - Check if composer packages are installed
- `checkComposerScript(string $scriptName, string $match): bool` - Check if a composer script contains a string
- `hasPostUpdateScript(string $match): bool` - Check post-update-cmd scripts
- `hasPostDeployScript(string $match): bool` - Check ci-deploy-post scripts
- `getComposerJson(): ?array` - Get parsed composer.json
- `getComposerPhpVersion(): ?string` - Extract PHP version from composer.json

### NPM/Package.json Checks
- `checkNpmPackages(string|array $packages, string $packageType = 'devDependencies'): bool`
- `checkNpmScript(string $scriptName, string $match): bool`
- `getPackageJson(): ?array`

### Configuration File Checks
- `getPhpunitXml(): \SimpleXMLElement|false|null`
- `checkPhpunitEnvVar(string $name, string $expectedValue): bool`
- `getGitlabCiData(): array` - Parse .gitlab-ci.yml
- `getDdevConfig(): ?array` - Parse .ddev/config.yaml
- `getReleaseItConfig(): ?array` - Parse .release-it.json

### Schedule Checks
- `hasScheduleEntry(string $command): bool` - Check if a command is scheduled
- `checkPackageWithSchedule(string $package, string|array $scheduleCommands): CheckResult` - Package + schedule validation

### Comments
- `addComment(string $comment): void` - Add error message shown to user
- `getComments(): array` - Get all comments

## Check Result Types

- `CheckResult::PASS` - Check passed successfully
- `CheckResult::FAIL` - Check failed, will increment error count and fail the command
- `CheckResult::WARN` - Optional check not configured (e.g., Pennant or Sentry if not installed)

## Periodic Checks

Some requirements can't be verified statically — they require a developer to perform a manual task on a schedule. Use `AbstractPeriodicCheck` for these.

### Adding a Periodic Check

Extend `AbstractPeriodicCheck` instead of `AbstractCheck`:

```php
class RunsMyTaskCheck extends AbstractPeriodicCheck
{
    // interval() defaults to 30 days — override only if a different period is needed
    public function interval(): CarbonInterval
    {
        return CarbonInterval::days(14);
    }

    public function isApplicable(): bool
    {
        // Return false to skip the periodic check entirely (yields WARN)
        // Use this to guard on optional packages:
        return $this->checkComposerPackages('vendor/package');
    }

    public function promptDescription(): string
    {
        return "Run 'php artisan my:command' to keep X up to date.";
    }
}
```

- `interval()` — defaults to 30 days in `AbstractPeriodicCheck`; override to change
- `isApplicable()` — defaults to `true`; return `false` to yield `WARN` (e.g., optional package not installed)
- `promptDescription()` — shown to the developer in the interactive command
- `check()` is `final` in `AbstractPeriodicCheck` — do not override; use `isApplicable()` for preconditions

### How periodic state is stored

Timestamps are persisted in `config/baseline.php` under a `periodic` key by `PeriodicStateManager`. The file is rewritten via `PhpFileWriter::writeConfig` (nikic/php-parser) each time a check is confirmed. `PeriodicStateManager` reads directly via `require` (bypassing Laravel's config cache) so state is always fresh.

The npm runner keeps the same two keys in `.baseline.json` at the project root, since a JS project has no `config/` directory to write a PHP file into.

### Running periodic checks

```bash
# Interactive: guides through all expired periodic checks
php artisan limenet:laravel-baseline:periodic

# CI: fails for any expired periodic check (non-interactive)
php artisan limenet:laravel-baseline:check
```

## Commit Conventions

> **Note:** `resources/boost/guidelines/core.blade.php` is the guideline this package *ships to
> consumer projects* — including its "do not use Conventional Commits" rule. That rule applies to
> those consumers, **not** to this repository. For commits in *this* repo, follow the Conventional
> Commits convention below; it drives the release tooling. Wherever `core.blade.php` and this
> `CLAUDE.md` disagree, `CLAUDE.md` governs work on this repo.

**Use [Conventional Commits](https://www.conventionalcommits.org/).** The release tooling derives the
next version number *and* the changelog directly from commit messages, so the message format is not
cosmetic — it drives the release.

Format: `<type>(<optional scope>): <description>`

Common types:

- `feat:` — a new feature → triggers a **minor** bump and appears under "Features" in the changelog.
- `fix:` — a bug fix → triggers a **patch** bump and appears under "Bug Fixes".
- `chore:`, `docs:`, `test:`, `refactor:`, `ci:`, `build:`, `style:`, `perf:` — no release on their
  own; `perf:` is listed in the changelog.
- Breaking change → append `!` after the type (e.g. `feat!:`) or add a `BREAKING CHANGE:` footer →
  triggers a **major** bump.

**Scope the commit** so the changelog stays legible to two audiences: `feat(php|js|policy): …`.
A single version covers both artifacts, so a PHP-only `feat:` also bumps the npm package — the
scope is what tells a reader whether their side actually changed. `policy` legitimately means both.

Guidelines:

- Write the description in the imperative mood ("add check", not "added check").
- When adding a check, prefer `feat: add <checkName> check`; when fixing check behaviour, `fix: ...`.
- Anything not user-facing (internal tooling, tests, formatting) should use a non-releasing type so
  it doesn't inflate the version or clutter the changelog.

## Releases

Releases are cut with [release-it](https://github.com/release-it/release-it) — this repo dogfoods the
same `creating-a-release` skill it ships to consumers. Install the Node tooling once
(`npm install`), then run:

```bash
GITHUB_TOKEN=… npm run release
```

This single command, driven by `.release-it.json`:

1. **Picks the version** from the conventional commits since the last tag (via
   `@release-it/conventional-changelog`) — override only if needed.
2. **Writes the version** into `composer.json` (`@release-it/bumper`); `composer.json` is the single
   source of truth.
3. **Writes `CHANGELOG.md`** — the new section is generated from the conventional commits and
   committed *in the same release commit*, so each tag is self-contained.
4. **Tags** as `v${version}` (always `v`-prefixed) and pushes.
5. **Creates the GitHub release** with the generated notes as its body.

A valid `GITHUB_TOKEN` (with `repo` scope) must be in the environment, otherwise release-it can't
create the GitHub release via the API and falls back to opening a browser.

`@release-it/bumper` writes the version into **both** `composer.json` and `package.json`, keeping the
two artifacts in lockstep — the same version means the same `policy/`, which is the only
compatibility guarantee available given they share a file rather than a dependency edge.

**release-it publishes to npm** as the last step of the same run (`"npm": {"publish": true}`), so
one command produces the tag, the GitHub release and the npm version. Two details make that safe
enough to keep in one place:

- The npm plugin's *init* checks — registry reachable, authenticated, and a collaborator on
  `@limenet-ch/baseline` — run **before** anything is bumped or tagged, so the usual reason a
  publish fails is caught while a release is still cancellable.
- `allowSameVersion` is on because `@release-it/bumper` is an *external* plugin and therefore bumps
  first: by the time the npm plugin runs `npm version`, `package.json` already carries the new
  version, and without the flag npm would refuse the no-op.

If a publish still fails after the tag was pushed, do **not** retag — npm versions are immutable and
Packagist is already serving that tag. Check out the tag and re-run `npm publish` by hand once the
cause is fixed; `prepack` rebuilds `js/dist` on the way.
