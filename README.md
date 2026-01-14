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
- **`hasCompleteRectorConfiguration()`** - Validates comprehensive Rector configuration

### IDE & Developer Tools
- **`usesIdeHelpers()`** - Validates Laravel IDE Helper is configured
- **`usesLaravelBoost()`** - Validates Laravel Boost AI development tool
- **`usesLimenetPintConfig()`** - Validates custom Laravel Pint formatting standards

### Laravel Features & Monitoring
- **`usesLaravelHorizon()`** - Validates Laravel Horizon Redis queue manager
- **`usesLaravelPennant()`** - Warns if Laravel Pennant feature flags are missing (optional)
- **`usesLaravelPulse()`** - Validates Laravel Pulse application monitoring
- **`usesLaravelTelescope()`** - Validates Laravel Telescope request debugging
- **`usesSpatieHealth()`** - Validates Spatie Health check monitoring
- **`usesSpatieBackup()`** - Validates Spatie Backup database backups

### Infrastructure & Dependencies
- **`usesPredis()`** - Validates Predis Redis client is installed
- **`isLaravelVersionMaintained()`** - Validates Laravel 11+ is used
- **`doesNotUseSail()`** - Validates Sail is NOT used (negative check)

### CI/CD & Deployment
- **`hasCiJobs()`** - Validates GitLab CI pipeline jobs are properly configured
- **`callsSentryHook()`** - Warns if Sentry error tracking is missing (optional)
- **`phpVersionMatchesCi()`** - Validates PHP version consistency with CI configuration
- **`isCiLintComplete()`** - Validates complete linting pipeline
- **`doesNotUseIgnition()`** - Validates Ignition debugger is NOT installed

### Local Development
- **`phpVersionMatchesDdev()`** - Validates PHP version consistency with DDEV
- **`ddevHasPcovPackage()`** - Validates DDEV coverage configuration
- **`ddevMutagenIgnoresNodeModules()`** - Validates DDEV Mutagen sync configuration

### Build & Release
- **`bumpsComposer()`** - Validates automatic composer dependency bumping
- **`usesReleaseIt()`** - Validates automated release management
- **`hasNpmScripts()`** - Validates required npm build scripts

### Security & Configuration
- **`hasEncryptedEnvFile()`** - Validates encrypted environment file exists
- **`hasGuidelinesUpdateScript()`** - Validates baseline guidelines update script
- **`callsBaseline()`** - Validates self-validation runs after updates

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
