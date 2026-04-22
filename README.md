# Laravel Baseline

[![Latest Version on Packagist](https://img.shields.io/packagist/v/limenet/laravel-baseline.svg?style=flat-square)](https://packagist.org/packages/limenet/laravel-baseline)
[![run-tests](https://github.com/limenet/laravel-baseline/actions/workflows/run-tests.yml/badge.svg)](https://github.com/limenet/laravel-baseline/actions/workflows/run-tests.yml)
[![Fix PHP code style issues](https://github.com/limenet/laravel-baseline/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/limenet/laravel-baseline/actions/workflows/fix-php-code-style-issues.yml)
[![codecov](https://codecov.io/gh/limenet/laravel-baseline/graph/badge.svg?token=Q57FG1L28A)](https://codecov.io/gh/limenet/laravel-baseline)
[![Total Downloads](https://img.shields.io/packagist/dt/limenet/laravel-baseline.svg?style=flat-square)](https://packagist.org/packages/limenet/laravel-baseline)

Checks your Laravel installation against a highly opinionated baseline.


## Installation

You can install the package via composer:

```bash
composer require limenet/laravel-baseline
```


You can publish the config file with:

```bash
php artisan vendor:publish --tag="laravel-baseline-config"
```

## Usage

```json
"post-update-cmd": [
    "@php artisan limenet:laravel-baseline"
],
```

## Checks

This package validates your Laravel installation against the following checks:

### Testing & Quality Tools
- **`usesPest()`** - Validates Pest testing framework is configured (not PHPUnit directly)
- **`usesRector()`** - Validates Rector automated code modernization is installed
- **`usesLarastan()`** - Validates Larastan static analysis tool is configured
- **`usesPhpstanExtensions()`** - Validates PHPStan extensions are installed
- **`phpstanLevelAtLeastEight()`** - Validates PHPStan is configured to at least level 8
- **`usesPhpInsights()`** - Validates PHP Insights code quality analysis is configured
- **`checkPhpunit()`** - Validates PHPUnit configuration with coverage reports
- **`hasRectorConfigWithComposerBased()`** - Validates Rector `withComposerBased(phpunit, symfony, laravel)` is configured
- **`hasRectorConfigWithPreparedSets()`** - Validates Rector `withPreparedSets(deadCode, codeQuality, codingStyle, typeDeclarations, privatization, instanceOf, earlyReturn)` is configured
- **`hasRectorConfigWithImportNames()`** - Validates Rector `withImportNames(importShortClasses: false)` is configured
- **`hasRectorConfigWithPhpSets()`** - Validates Rector `withPhpSets()` is called
- **`hasRectorConfigWithAttributesSets()`** - Validates Rector `withAttributesSets()` is called
- **`hasRectorConfigWithSetProviders()`** - Validates Rector `withSetProviders(LaravelSetProvider)` is configured
- **`hasRectorConfigWithRules()`** - Validates Rector `withRules([AddGenericReturnTypeToRelationsRector])` is configured
- **`hasRectorConfigWithSets()`** - Validates Rector `withSets([LaravelBaselineSetList])` is configured
- **`hasRectorConfigWithPaths()`** - Validates Rector `withPaths([app, database, routes, tests])` is configured
- **`hasRectorConfigWithSkip()`** - Validates Rector `withSkip([TablePropertyToTableAttributeRector])` is configured

### IDE & Developer Tools
- **`hasClaudeSettingsWithLaravelSimplifier()`** - Validates Claude Code settings include Laravel Simplifier plugin
- **`usesIdeHelpers()`** - Validates Laravel IDE Helper is configured
- **`usesLaravelAdminer()`** - Warns if Laravel Adminer database UI is missing (optional), validates TFA confirmation and configuration when installed
- **`usesLaravelBoost()`** - Validates Laravel Boost AI development tool
- **`usesLimenetPintConfig()`** - Validates custom Laravel Pint formatting standards

### Laravel Features & Monitoring
- **`usesLaravelHorizon()`** - Validates Laravel Horizon Redis queue manager
- **`usesLaravelPennant()`** - Warns if Laravel Pennant feature flags are missing (optional)
- **`usesLaravelPulse()`** - Validates Laravel Pulse application monitoring
- **`usesLaravelTelescope()`** - Validates Laravel Telescope request debugging
- **`usesSpatieHealthSetup()`** - Validates Spatie Health packages, schedules, s3_health disk, and result store configuration
- **`usesSpatieHealthHasCoreChecks()`** - Validates core health checks (CacheCheck, CpuLoadCheck, DatabaseCheck, DebugModeCheck, EnvironmentCheck, HorizonCheck, RedisCheck, ScheduleCheck, UsedDiskSpaceCheck) are registered
- **`usesSpatieHealthHasLaravelVersionCheck()`** - Validates LaravelVersionCheck is registered in Health::checks()
- **`usesSpatieHealthHasPhpVersionCheck()`** - Validates PhpVersionCheck is registered in Health::checks()
- **`usesSpatieHealthHasReleaseAgeCheck()`** - Validates ReleaseAgeCheck is registered in Health::checks()
- **`usesSpatieBackup()`** - Validates Spatie Backup database backups with comprehensive config validation

### Infrastructure & Dependencies
- **`usesPredis()`** - Validates Predis Redis client is installed
- **`isLaravelVersionMaintained()`** - Validates Laravel 11+ is used
- **`doesNotUseSail()`** - Validates Sail is NOT used
- **`doesNotUseHorizonWatcher()`** - Validates Spatie Horizon Watcher is NOT installed
- **`doesNotUseGreaterThanOrEqualConstraints()`** - Validates no `>=` version constraints in composer.json (use `^` or `~` instead)

### CI/CD & Deployment
- **`hasCiJobs()`** - Validates GitLab CI pipeline jobs are properly configured
- **`callsSentryHook()`** - Warns if Sentry error tracking is missing (optional)
- **`phpVersionMatchesCi()`** - Validates PHP version consistency with CI configuration
- **`isCiLintComplete()`** - Validates complete linting pipeline
- **`doesNotUseIgnition()`** - Validates Ignition debugger is NOT installed

### Local Development
- **`phpVersionMatchesDdev()`** - Validates PHP version consistency with DDEV
- **`ddevHasPcovPackage()`** - Validates DDEV coverage configuration
- **`ddevHasRedisAddon()`** - Validates DDEV Redis addon is installed and at minimum version 2.2.0
- **`ddevMutagenIgnoresNodeModules()`** - Validates DDEV Mutagen sync configuration

### Build & Release
- **`bumpsComposer()`** - Validates automatic composer dependency bumping
- **`usesReleaseIt()`** - Validates automated release management
- **`hasNpmScripts()`** - Validates required npm build scripts

### Security & Configuration
- **`hasDailyLogging()`** - Validates logging uses `daily` channel (directly or via `stack`)
- **`hasEncryptedEnvFile()`** - Validates encrypted environment file exists
- **`hasGuidelinesUpdateScript()`** - Validates baseline guidelines update script
- **`callsBaseline()`** - Validates self-validation runs after updates
- **`isInstalledAsRegularDependency()`** - Validates `limenet/laravel-baseline` is in `require` (not `require-dev`)
- **`usesLaravelLang()`** - Validates `laravel-lang/lang` dev dependency is installed with `lang:update` and pint in post-update scripts

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [Linus Metzler](https://github.com/limenet)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
