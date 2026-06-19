# Development Guidelines

**Note:** Keep this file up-to-date as patterns evolve. If you add new helper methods, configuration parsers, or change the workflow, update the relevant sections in this document.

## Architecture Overview

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
└── State/
    └── StateManager.php             # Reads/writes config/baseline.php for periodic state

resources/
└── boost/                          # Laravel Boost resources shipped to consumers
    ├── guidelines/core.blade.php   # Always-on AI guideline (dev loop, conventions)
    └── skills/<name>/SKILL.md      # On-demand AI skills (e.g. creating-a-release)
```

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

### 5. Run the Full Test Suite

Always run the full test suite after adding a new check:

```bash
composer test
```

Adding a new check to the registry can affect other tests (e.g., tests that count total checks or iterate over all registered checks). Do not rely solely on running tests for the new check.

When **renaming** a check class, the `name()` return value changes automatically (it is derived from the class name). Update the README.md entry to match — the `ReadmeChecksTest` will catch any mismatch.

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

Timestamps are persisted in `config/baseline.php` under a `periodic` key by `StateManager`. The file is rewritten using `var_export` each time a check is confirmed. `StateManager` reads directly via `require` (bypassing Laravel's config cache) so state is always fresh.

### Running periodic checks

```bash
# Interactive: guides through all expired periodic checks
php artisan limenet:laravel-baseline:periodic

# CI: fails for any expired periodic check (non-interactive)
php artisan limenet:laravel-baseline:check
```
