# Development Guidelines

**Note:** Keep this file up-to-date as patterns evolve. If you add new helper methods, configuration parsers, or change the workflow, update the relevant sections in this document.

## Architecture Overview

The codebase uses a **one class per check** architecture:

```
src/Checks/
├── CheckInterface.php           # Contract for all checks
├── AbstractCheck.php            # Base class with helper methods
├── CheckRegistry.php            # Registry of all check classes
├── CommentCollector.php         # Manages comments for a check run
└── Checks/                      # Individual check classes (35 total)
    ├── BumpsComposerCheck.php
    ├── CallsBaselineCheck.php
    └── ...
```

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

Add tests to [tests/CheckerTest.php](tests/CheckerTest.php) covering:

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

**Testing comments:** If you need to verify error messages
```php
it('myNew provides helpful comment when script is missing', function (): void {
    bindFakeComposer(['vendor/package' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(MyNewCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('Missing script in composer.json...');
});
```

### 4. Document in README.md

Add check documentation to [README.md](README.md) under the appropriate category.

### 5. Run the Full Test Suite

Always run the full test suite after adding a new check:

```bash
composer test
```

Adding a new check to the registry can affect other tests (e.g., tests that count total checks or iterate over all registered checks). Do not rely solely on running tests for the new check.

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
