# Development Guidelines

**Note:** Keep this file up-to-date as patterns evolve. If you add new helper methods, configuration parsers, or change the workflow, update the relevant sections in this document.

## When Adding a New Check

### 1. Create the Check Method in Checker.php

- Add a public method that returns `CheckResult` to [src/Checks/Checker.php](src/Checks/Checker.php)
- Method name should be descriptive and follow camelCase (e.g., `usesPest()`, `hasCompleteRectorConfiguration()`)
- Return one of: `CheckResult::PASS`, `CheckResult::FAIL`, or `CheckResult::WARN`
- Use `$this->addComment($message)` to provide helpful error messages when a check fails
- Error messages should be actionable and specific, telling the developer what file to modify and what to add/change

**Example check structure:**
```php
public function myNewCheck(): CheckResult
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
```

### 2. Register the Check in LaravelBaselineCommand

- Add `$checker->methodName(...)` to the foreach array in [src/Commands/LaravelBaselineCommand.php](src/Commands/LaravelBaselineCommand.php:18-53)
- Keep the array alphabetically sorted for maintainability
- The test suite will automatically verify all check methods are registered

### 3. Write Comprehensive Tests

Add tests to [tests/CheckerTest.php](tests/CheckerTest.php) covering:

**Absence test:** Check fails when package/configuration is missing
```php
it('myCheck fails when package is missing', function (): void {
    bindFakeComposer(['vendor/package' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->myNewCheck())->toBe(CheckResult::FAIL);
});
```

**Presence test:** Check passes when properly configured
```php
it('myCheck passes when properly configured', function (): void {
    bindFakeComposer(['vendor/package' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan some:command']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $checker = new Checker(makeCommand());
    expect($checker->myNewCheck())->toBe(CheckResult::PASS);
});
```

**Edge cases:** Test partial configurations, wrong values, etc.
```php
it('myCheck fails when script is missing despite package being installed', function (): void {
    bindFakeComposer(['vendor/package' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $checker = new Checker(makeCommand());
    expect($checker->myNewCheck())->toBe(CheckResult::FAIL);
});
```

### 4. Document in README.md

Add check documentation to [README.md](README.md) under the appropriate category:

```markdown
- **`myNewCheck()`** - Brief description of what this validates
```

Categories include:
- Testing & Quality Tools
- IDE & Developer Tools
- Laravel Features & Monitoring
- Infrastructure & Dependencies
- CI/CD & Deployment
- Local Development
- Build & Release
- Security & Configuration

Keep descriptions concise but clear about what is being validated.

## Available Helper Methods in Checker

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
- `resetComments(): void` - Clear comments (done automatically between checks)
- `getComments(): array` - Get all comments (used by command)

## Check Result Types

- `CheckResult::PASS` - Check passed successfully
- `CheckResult::FAIL` - Check failed, will increment error count and fail the command
- `CheckResult::WARN` - Optional check not configured (e.g., Pennant or Sentry if not installed)
