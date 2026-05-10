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

Add to your `composer.json` to run checks (and auto-fix) after every `composer update`:

```json
"post-update-cmd": [
    "@php artisan limenet:laravel-baseline:check --fix"
],
```

### Running checks

```bash
# Check only — report issues without making changes
php artisan limenet:laravel-baseline:check

# Auto-fix — apply all safe automatic fixes, then report remaining issues
php artisan limenet:laravel-baseline:check --fix
```

**Checks marked 🔧** below support `--fix`. When `--fix` is used:
- Fully fixable checks: all conditions are applied automatically.
- Partially fixable checks *(requires package installed first)*: configuration/script entries are fixed once the package is installed via `composer require`.
- Non-fixable checks: report the issue with an actionable message.

## Checks

This package validates your Laravel installation against the following checks:

### Testing & Quality Tools
- **`usesPest()`** - Validates Pest testing framework is configured (not PHPUnit directly)
- 🔧 **`usesRector()`** - Validates Rector automated code modernization is installed *(partial: fixes ci-lint script if packages installed)*
- **`usesLarastan()`** - Validates Larastan static analysis tool is configured
- **`usesPhpstanExtensions()`** - Validates PHPStan extensions are installed
- **`phpstanLevelAtLeastEight()`** - Validates PHPStan is configured to at least level 8
- 🔧 **`usesPhpInsights()`** - Validates PHP Insights code quality analysis is configured *(partial: fixes ci-lint scripts if package installed)*
- 🔧 **`checkPhpunit()`** - Validates PHPUnit configuration with coverage reports *(adds missing XML nodes and APP_KEY)*
- 🔧 **`hasRectorConfigWithComposerBased()`** - Validates Rector `withComposerBased(phpunit, symfony, laravel)` is configured *(appends call to rector.php)*
- 🔧 **`hasRectorConfigWithConfiguredRules()`** - Validates Rector `withConfiguredRule()` calls are present for `RouteActionCallableRector` and `WhereToWhereLikeRector` *(appends calls to rector.php)*
- 🔧 **`hasRectorConfigWithPreparedSets()`** - Validates Rector `withPreparedSets(deadCode, codeQuality, codingStyle, typeDeclarations, privatization, instanceOf, earlyReturn)` is configured *(appends call to rector.php)*
- 🔧 **`hasRectorConfigWithImportNames()`** - Validates Rector `withImportNames(importShortClasses: false)` is configured *(appends call to rector.php)*
- 🔧 **`hasRectorConfigWithPhpSets()`** - Validates Rector `withPhpSets()` is called *(appends call to rector.php)*
- 🔧 **`hasRectorConfigWithAttributesSets()`** - Validates Rector `withAttributesSets()` is called *(appends call to rector.php)*
- 🔧 **`hasRectorConfigWithSetProviders()`** - Validates Rector `withSetProviders(LaravelSetProvider)` is configured *(appends call to rector.php)*
- 🔧 **`hasRectorConfigWithRules()`** - Validates Rector `withRules([AddGenericReturnTypeToRelationsRector, MinutesToSecondsInCacheRector, UseForwardsCallsTraitRector])` is configured *(appends call to rector.php)*
- 🔧 **`hasRectorConfigWithSets()`** - Validates Rector `withSets([LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS, LaravelSetList::LARAVEL_*])` is configured with all required sets *(appends call to rector.php)*
- 🔧 **`hasRectorConfigWithPaths()`** - Validates Rector `withPaths([app, database, routes, tests])` is configured *(appends call to rector.php)*
- 🔧 **`hasRectorConfigWithSkip()`** - Validates Rector `withSkip()` contains required skipped rules (always: 6 Laravel rules; Laravel 13+: TablePropertyToTableAttributeRector; when server.php exists: ServerVariableToRequestFacadeRector) *(appends call to rector.php)*

### IDE & Developer Tools
- 🔧 **`hasClaudeSettingsWithLaravelSimplifier()`** - Validates Claude Code settings include Laravel Simplifier plugin *(creates/merges `.claude/settings.json`)*
- 🔧 **`usesIdeHelpers()`** - Validates Laravel IDE Helper is configured *(partial: adds post-update scripts if package installed)*
- **`usesLaravelAdminer()`** - Warns if Laravel Adminer database UI is missing (optional), validates TFA confirmation and configuration when installed
- 🔧 **`usesLaravelBoost()`** - Validates Laravel Boost AI development tool *(partial: fixes boost.json and post-update script if package installed)*
- **`runsBoostUpdate()`** *(periodic, every 30 days)* - Warns if Laravel Boost is not installed; when installed, fails until a developer confirms running `php artisan boost:update --discover` via `limenet:laravel-baseline:periodic`
- 🔧 **`usesLimenetPintConfig()`** - Validates custom Laravel Pint formatting standards *(partial: adds post-update script if package installed)*

### Laravel Features & Monitoring
- 🔧 **`usesLaravelHorizon()`** - Validates Laravel Horizon Redis queue manager *(partial: adds ci-deploy-post script if package installed)*
- **`usesLaravelPennant()`** - Warns if Laravel Pennant feature flags are missing (optional)
- 🔧 **`usesLaravelPulse()`** - Validates Laravel Pulse application monitoring *(partial: adds PULSE_ENABLED=false to phpunit.xml if package installed)*
- 🔧 **`usesLaravelTelescope()`** - Validates Laravel Telescope request debugging *(partial: adds post-update script and TELESCOPE_ENABLED=false to phpunit.xml if package installed)*
- **`usesSpatieHealthSetup()`** - Validates Spatie Health packages, schedules, s3_health disk, and result store configuration
- 🔧 **`usesSpatieHealthHasCoreChecks()`** - Validates core health checks (CacheCheck, CpuLoadCheck, DatabaseCheck, DebugModeCheck, EnvironmentCheck, HorizonCheck, RedisCheck, ScheduleCheck, UsedDiskSpaceCheck) are registered *(adds missing checks to Health::checks() in AppServiceProvider)*
- 🔧 **`usesSpatieHealthHasLaravelVersionCheck()`** - Validates LaravelVersionCheck is registered in Health::checks() *(adds to AppServiceProvider)*
- 🔧 **`usesSpatieHealthHasPhpVersionCheck()`** - Validates PhpVersionCheck is registered in Health::checks() *(adds to AppServiceProvider)*
- 🔧 **`usesSpatieHealthHasReleaseAgeCheck()`** - Validates ReleaseAgeCheck is registered in Health::checks() *(adds to AppServiceProvider)*
- **`usesSpatieBackup()`** - Validates Spatie Backup database backups with comprehensive config validation

### Infrastructure & Dependencies
- **`usesPredis()`** - Validates Predis Redis client is installed
- **`isLaravelVersionMaintained()`** - Validates Laravel 11+ is used
- 🔧 **`doesNotUseSail()`** - Validates Sail is NOT used *(partial: deletes docker-compose.yml; run `composer remove laravel/sail` manually)*
- **`doesNotUseSpatiePasskeysWithFortify()`** - Fails if both `spatie/laravel-passkeys` and `laravel/fortify` are installed, as they overlap in authentication responsibility
- **`doesNotUseHorizonWatcher()`** - Validates Spatie Horizon Watcher is NOT installed
- 🔧 **`doesNotUseGreaterThanOrEqualConstraints()`** - Validates no `>=` version constraints in composer.json (use `^` or `~` instead) *(replaces `>=X.Y` with `^X.Y` in composer.json)*

### CI/CD & Deployment
- **`hasCiJobs()`** - Validates GitLab CI pipeline jobs are properly configured
- 🔧 **`hasTrivyConfig()`** - Validates Trivy security scanning CI job and `trivy.yaml` configuration (scanners + severity) *(creates/merges trivy.yaml and adds CI job)*
- **`callsSentryHook()`** - Warns if Sentry error tracking is missing (optional)
- **`phpVersionMatchesCi()`** - Validates PHP version consistency with CI configuration
- **`isCiLintComplete()`** - Validates complete linting pipeline
- **`doesNotUseIgnition()`** - Validates Ignition debugger is NOT installed

### Local Development
- **`phpVersionMatchesDdev()`** - Validates PHP version consistency with DDEV
- 🔧 **`ddevHasPcovPackage()`** - Validates DDEV coverage configuration *(adds pcov to webimage_extra_packages and creates .ddev/php/90-custom.ini)*
- **`ddevHasRedisAddon()`** - Validates DDEV Redis addon is installed and at minimum version 2.2.0
- 🔧 **`ddevMutagenIgnoresNodeModules()`** - Validates DDEV Mutagen sync configuration *(creates mutagen.yml and fixes .gitignore)*

### Build & Release
- 🔧 **`bumpsComposer()`** - Validates automatic composer dependency bumping *(adds `composer bump` to post-update-cmd)*
- 🔧 **`usesReleaseIt()`** - Validates automated release management *(partial: creates/fixes .release-it.json and adds release npm script if packages installed)*
- **`hasNpmScripts()`** - Validates required npm build scripts

### Security & Configuration
- 🔧 **`modelShouldBeStrict()`** - Validates `Model::shouldBeStrict()` is called in AppServiceProvider with `true`, no argument, or a dynamic expression (not `false`) *(adds `Model::shouldBeStrict(! app()->isProduction())` to boot())*
- 🔧 **`formRequestFailOnUnknownFields()`** - Validates `FormRequest::failOnUnknownFields()` is called in AppServiceProvider (Laravel ≥13.6 only; warns on older versions) *(adds `FormRequest::failOnUnknownFields(! app()->isProduction())` to boot())*
- **`hasDailyLogging()`** - Validates logging uses `daily` channel (directly or via `stack`)
- **`hasEncryptedEnvFile()`** - Validates encrypted environment file exists
- 🔧 **`hasGuidelinesUpdateScript()`** - Validates baseline guidelines update script *(adds to post-update-cmd, ordered before boost:update)*
- 🔧 **`callsBaseline()`** - Validates self-validation runs after updates *(adds/upgrades post-update-cmd entry to include `--fix`)*
- **`callsPeriodicBaseline()`** - Validates `php artisan limenet:laravel-baseline:periodic` is in the `post-update-cmd` scripts
- 🔧 **`isInstalledAsRegularDependency()`** - Validates `limenet/laravel-baseline` is in `require` (not `require-dev`) *(moves from require-dev to require in composer.json)*
- 🔧 **`usesLaravelLang()`** - Validates `laravel-lang/lang` dev dependency is installed with `lang:update` and pint in post-update scripts *(partial: adds post-update scripts if package in require-dev)*

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
