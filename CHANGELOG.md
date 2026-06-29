# Changelog

All notable changes to `laravel-baseline` will be documented in this file.


## [2.1.5](https://github.com/limenet/laravel-baseline/compare/v2.1.4...v2.1.5) (2026-06-29)

## [2.1.4](https://github.com/limenet/laravel-baseline/compare/v2.1.3...v2.1.4) (2026-06-20)

### Bug Fixes

* **boost:** discourage conventional commits and command chaining in core guideline ([2faa8bd](https://github.com/limenet/laravel-baseline/commit/2faa8bdc236530f441f4bdcad8c9efb70769102d))

## [2.1.3](https://github.com/limenet/laravel-baseline/compare/v2.1.2...v2.1.3) (2026-06-20)

### Bug Fixes

* **boost:** remove conventional commits section from core guideline ([e901b09](https://github.com/limenet/laravel-baseline/commit/e901b093c373196f9faf70b4d7b43e57b3043d51))

## [2.1.2](https://github.com/limenet/laravel-baseline/compare/v2.1.1...v2.1.2) (2026-06-20)

### Bug Fixes

* **boost:** remove repo-specific release details from skill ([b7eeaa9](https://github.com/limenet/laravel-baseline/commit/b7eeaa9815cce8c4fd8b6c229618ebceea7b78b5))
* **doesNotHaveGuidelinesScript:** detect and remove stale post-update-cmd entry ([d0bc4b7](https://github.com/limenet/laravel-baseline/commit/d0bc4b73065d549e44153fef9a7adf9c7ecc25e0))
* **usesPhpInsights:** auto-fix disable-security-check via AST rewrite ([11be4b6](https://github.com/limenet/laravel-baseline/commit/11be4b63327852de8e54d8440fde049ee85bdee2))

## [2.1.1](https://github.com/limenet/laravel-baseline/compare/v2.1.0...v2.1.1) (2026-06-20)

### Bug Fixes

* **usesPhpInsights:** enforce disable-security-check in insights config ([45006f4](https://github.com/limenet/laravel-baseline/commit/45006f4f350df822ee2196ac4d743398ff44d9dd))

## [2.1.0](https://github.com/limenet/laravel-baseline/compare/v2.0.13...v2.1.0) (2026-06-19)

### What's Changed

* Add check `UsesSpatieHealthCacheCheckCacheStoreCheck` ensuring Spatie Health's `CacheCheck` is pinned to the `health-checks` store via `->driver('health-checks')` (the cache-store base class now supports a configurable method name).
* Ship AI-facing knowledge through Laravel Boost's native `resources/boost/` mechanism: an always-on dev-loop guideline (ci-lint, Pest, DDEV-first workflow) and an on-demand `creating-a-release` skill.
* Remove the `HasGuidelinesUpdateScriptCheck` check and the `limenet:laravel-baseline:guidelines` command, now superseded by Boost discovery.
* Allow the `code-review` skill (`Skill(code-review)` and `Skill(code-review:*)`) without a permission prompt in `AllowsToolingInClaudeSettingsCheck`.
* Raise the `laravel/framework` dev constraint to `^13.15.0`.

## [2.0.13](https://github.com/limenet/laravel-baseline/compare/v2.0.12...v2.0.13) (2026-06-07)

### What's Changed

* Add check `AllowsToolingInClaudeSettingsCheck` that fixes `.claude/settings.json` to allow read-only/dev-loop `ddev artisan`, `composer`, and `npm` commands without a permission prompt.
* Add check `DeniesEnvReadsInClaudeSettingsCheck` that denies reading `.env` and every encrypted environment's `.env.{env}` (while keeping `.env.example` readable) to keep Claude away from secrets.
* Add check `RunsCiLintHookInClaudeSettingsCheck` requiring a `Stop` hook that runs `ddev composer run ci-lint`.
* Introduce shared `AbstractClaudeSettingsCheck` base class for reading, merging, and writing `.claude/settings.json`.

## [2.0.12](https://github.com/limenet/laravel-baseline/compare/v2.0.11...v2.0.12) (2026-06-03)

### What's Changed

* Add check `UpdatesDdevAddonsCheck` that fails when any DDEV add-on (per `.ddev/addon-metadata/*/manifest.yaml`) was installed more than 3 months ago, prompting a `ddev add-on get` refresh.
* Bump dev dependencies (`larastan`, `laravel/framework`, `pestphp/pest`, `spatie/laravel-health`).

## [2.0.11](https://github.com/limenet/laravel-baseline/compare/v2.0.10...v2.0.11) (2026-05-28)

### What's Changed

* Extend `HasTrivyConfigCheck` to forbid a `severity` key in `trivy.yaml` (and remove it on `--fix`), deferring to Trivy's default severity behavior.

## [2.0.10](https://github.com/limenet/laravel-baseline/compare/v2.0.9...v2.0.10) (2026-05-28)

### What's Changed

* Rework `HasTrivyConfigCheck` to enforce a fuller `trivy.yaml`: `ignorefile`, `cache.dir`, `scan.disable-telemetry`, `disable-vex-notice`, `dependency-tree`, `scan.skip-files`, `scan.skip-dirs`, and `scan.scanners` (`misconfig`, `secret`, `vuln`), plus a `.gitignore` entry for the Trivy cache directory.

## [2.0.9](https://github.com/limenet/laravel-baseline/compare/v2.0.8...v2.0.9) (2026-05-27)

### What's Changed

* Add check `UsesSpatieHealthScheduleCheckHeartbeatCheck` requiring Spatie Health's `ScheduleCheck` to call `heartbeatMaxAgeInMinutes(2)` in `AppServiceProvider`, with `--fix` appending the call automatically.

## [2.0.8](https://github.com/limenet/laravel-baseline/compare/v2.0.7...v2.0.8) (2026-05-22)

### What's Changed

* Extend the health-check cache-store checks to also validate the store's `path` points at `storage_path(...)` and that a protective `.gitignore` (`*` / `!.gitignore`) exists at that path in `config/cache.php`.

## [2.0.7](https://github.com/limenet/laravel-baseline/compare/v2.0.6...v2.0.7) (2026-05-22)

### What's Changed

* Fix `UsesSpatieHealthQueueCheckHorizonQueuesCheck` to also collect queue names from Horizon's `defaults` supervisor block, not just environment-specific supervisors.

## [2.0.6](https://github.com/limenet/laravel-baseline/compare/v2.0.5...v2.0.6) (2026-05-17)

### What's Changed

* Add check `HasEditorconfigCheck` validating (and fixing) a project `.editorconfig`.
* Add check `LaravelBoostMcpUsesDdevCheck` ensuring the Laravel Boost MCP server is invoked through DDEV.
* Replace the Claude "Laravel Simplifier" enforcement with `DoesNotHaveLaravelSimplifierInClaudeSettingsCheck` and rename the skills check to `HasClaudeSettingsWithLaravelSkillsCheck`.
* Invert the periodic-baseline-on-update check into `DoesNotCallPeriodicBaselineOnUpdateCheck` (replacing the redundant `CallsPeriodicBaselineCheck`).
* Make several fixable checks write files only when a change is actually needed.

## [2.0.5](https://github.com/limenet/laravel-baseline/compare/v2.0.4...v2.0.5) (2026-05-14)

### What's Changed

* Make `--fix` re-verify with a dry `check()` first and only run `fix()` when there's an actual error, reporting a "(fixed)" status accurately.
* Improve the health-check cache-store and Horizon-queue checks for real-world integration scenarios.

## [2.0.4](https://github.com/limenet/laravel-baseline/compare/v2.0.3...v2.0.4) (2026-05-14)

### What's Changed

* Add health-check coverage checks: `UsesSpatieHealthQueueCheckCacheStoreCheck`, `UsesSpatieHealthQueueCheckHorizonQueuesCheck`, and `UsesSpatieHealthScheduleCheckCacheStoreCheck`, backed by new AST visitors.
* Require `QueueCheck`, `DatabaseConnectionCountCheck` (and the `doctrine/dbal` package) in the Spatie Health core-checks set.

## [2.0.3](https://github.com/limenet/laravel-baseline/compare/v2.0.2...v2.0.3) (2026-05-13)

### What's Changed

* Add `PhpFileWriter` to preserve original PHP formatting when fixable checks rewrite source files, refactoring the Rector, service-provider, and health-check base classes onto it.
* Fix namespaces and `use` placements in the Rector config checks.

## [2.0.2](https://github.com/limenet/laravel-baseline/compare/v2.0.1...v2.0.2) (2026-05-13)

### What's Changed

* Restore the `--fix` flag on `limenet:laravel-baseline:check`, which auto-applies safe fixes for `FixableInterface` checks, skips inapplicable periodic checks, and marks fixed items with a `🔧 (fixed)` indicator.

## [2.0.1](https://github.com/limenet/laravel-baseline/compare/v2.0.0...v2.0.1) (2026-05-13)

### What's Changed

* Remove the obsolete `LaravelBaselineCommand` class and rename its test file to `CheckCommandTest` following the v2.0.0 command rename. Internal cleanup only.

## [2.0.0](https://github.com/limenet/laravel-baseline/compare/1.3.6...v2.0.0) (2026-05-10)

### What's Changed

* Rename the main command from `limenet:laravel-baseline` to `limenet:laravel-baseline:check` and update the recommended `post-update-cmd` to `@php artisan limenet:laravel-baseline:check --fix` (breaking change).
* Add a `--fix` auto-fix system via the new `FixableInterface` / `AbstractFixableCheck`, where `check()` runs as a dry-run of `fix()`; many existing checks (Rector config, health checks, CI scripts, DDEV, Trivy, etc.) gained automatic remediation, fully or partially.
* Add periodic checks: `AbstractPeriodicCheck`, `PeriodicCheckInterface`, the `limenet:laravel-baseline:periodic` command, `PeriodicStateManager`, plus `CallsPeriodicBaselineCheck` (verifies the periodic command runs post-update) and `RunsBoostUpdateCheck` (prompts to run `boost:update --discover`).
* Add `ModelShouldBeStrictCheck` and `FormRequestFailOnUnknownFieldsCheck` (Laravel 13.6+), enforcing `Model::shouldBeStrict()` and `FormRequest::failOnUnknownFields()` calls in `AppServiceProvider` and rejecting a literal `false` argument.
* Add `DoesNotUseSpatiePasskeysWithFortifyCheck`, which fails if `spatie/laravel-passkeys` is installed alongside `laravel/fortify`, and a `HasClaudeSettingsWithLaravelSimplifierCheck` rule requiring `"laravel@laravel": true` in `.claude/settings.json` enabled plugins.
* Add the `hasRectorConfigWithConfiguredRules()` check (requires `withConfiguredRule()` for `RouteActionCallableRector` and `WhereToWhereLikeRector`) and bump dev dependencies (`laravel/framework` to `^13.8.0`, `pestphp/pest` to `^4.7.0`).

## [1.3.6](https://github.com/limenet/laravel-baseline/compare/1.3.5...1.3.6) (2026-05-03)

### What's Changed

* Expand the Rector config checks: `hasRectorConfigWithRules()` now requires `MinutesToSecondsInCacheRector` and `UseForwardsCallsTraitRector`, `hasRectorConfigWithSets()` validates the full Laravel set list, and `hasRectorConfigWithSkip()` now validates six always-required skipped rules plus conditional ones (`TablePropertyToTableAttributeRector` on Laravel 13+, `ServerVariableToRequestFacadeRector` when `server.php` exists).
* Add the `hasRectorConfigWithConfiguredRules()` check requiring `withConfiguredRule()` calls for `RouteActionCallableRector` and `WhereToWhereLikeRector`.

## [1.3.5](https://github.com/limenet/laravel-baseline/compare/1.3.4...1.3.5) (2026-05-03)

### What's Changed

* Remove the `usesSpatieHealthScheduleCheckConfiguration()` check (`UsesSpatieHealthScheduleCheckConfigurationCheck`) and its `heartbeatMaxAgeInMinutes` validation.

## [1.3.4](https://github.com/limenet/laravel-baseline/compare/1.3.3...1.3.4) (2026-05-03)

### What's Changed

* Add `HasTrivyConfigCheck`, validating a Trivy security-scan configuration including a required `scan.skip-dirs` list (`.ddev`, `node_modules`, `storage/logs`, `vendor`).
* Extract a shared `AbstractCiJobCheck` base class for CI-job-related checks.

## [1.3.3](https://github.com/limenet/laravel-baseline/compare/1.3.2...1.3.3) (2026-05-01)

### What's Changed

* Add `UsesSpatieHealthScheduleCheckConfigurationCheck` to reduce false positives by parsing the `AppServiceProvider` AST for the health `ScheduleCheck` configuration.

## [1.3.2](https://github.com/limenet/laravel-baseline/compare/1.3.1...1.3.2) (2026-04-22)

### What's Changed

* Remove the duplicate static `ReleaseAgeCheck` baseline check, leaving release-age validation to the registered Spatie Health check.

## [1.3.1](https://github.com/limenet/laravel-baseline/compare/1.3.0...1.3.1) (2026-04-22)

### What's Changed

* Rework release-age validation: replace the standalone baseline check with a `Spatie\Health` `ReleaseAgeCheck` (ok < 6 weeks, warning < 3 months, fail otherwise) and add `usesSpatieHealthHasReleaseAgeCheck()` to enforce it is registered in `Health::checks()`.

## [1.3.0](https://github.com/limenet/laravel-baseline/compare/1.2.25...1.3.0) (2026-04-22)

### What's Changed

* Split the monolithic `HasCompleteRectorConfigurationCheck` into ten focused checks (`hasRectorConfigWithComposerBased()`, `withPreparedSets()`, `withImportNames()`, `withPhpSets()`, `withAttributesSets()`, `withSetProviders()`, `withRules()`, `withSets()`, `withPaths()`, `withSkip()`) sharing a new `AbstractHasRectorConfigCheck` base.
* Split the monolithic Spatie Health check into `usesSpatieHealthSetup()`, `usesSpatieHealthHasCoreChecks()`, `usesSpatieHealthHasLaravelVersionCheck()`, and `usesSpatieHealthHasPhpVersionCheck()` via a shared `AbstractUsesSpatieHealthChecksCheck` base.
* Add a `releaseAge()` check warning when `composer.json` is older than 6 weeks and failing past 3 months.
* Drop Laravel Boost v1 support: `UsesLaravelBoostCheck` now only validates Boost v2 configuration, removing the v1 code path.
* Gate the Rector `withSkip([TablePropertyToTableAttributeRector])` requirement to Laravel 13+ only, and bump `dependabot/fetch-metadata` 3.0.0 to 3.1.0 plus dev-library updates.

## [1.2.25](https://github.com/limenet/laravel-baseline/compare/1.2.24...1.2.25) (2026-04-17)

### What's Changed

* Teach the health-checks AST visitor to resolve check classes used inside ternary expressions, preventing false negatives when health checks are registered conditionally.

## [1.2.24](https://github.com/limenet/laravel-baseline/compare/1.2.23...1.2.24) (2026-04-17)

### What's Changed

* Enable the previously commented-out Rector `withSkip` requirement, now enforcing `TablePropertyToTableAttributeRector` is skipped in the Rector config.

## [1.2.23](https://github.com/limenet/laravel-baseline/compare/1.2.22...1.2.23) (2026-04-01)

### What's Changed

* Allow `symfony/finder` and `symfony/yaml` 8.x alongside 7.4 in `composer.json`.
* Bump CI dependencies via dependabot (`dependabot/fetch-metadata`, `codecov/codecov-action`).

## [1.2.22](https://github.com/limenet/laravel-baseline/compare/1.2.21...1.2.22) (2026-03-26)

### What's Changed

* Fix health-check detection to follow chained method calls in `config/health.php` (e.g. `CpuLoadCheck::new()->failWhenLoadIsHigherInThePast...()`) by walking the method-call chain in `HealthChecksStaticCallVisitor`, so fluently configured checks are now correctly recognized.

## [1.2.21](https://github.com/limenet/laravel-baseline/compare/1.2.20...1.2.21) (2026-03-23)

### What's Changed

* Add `IsInstalledAsRegularDependencyCheck` ensuring `limenet/laravel-baseline` is listed under `require` (not `require-dev`) in `composer.json`.

## [1.2.20](https://github.com/limenet/laravel-baseline/compare/1.2.19...1.2.20) (2026-03-22)

### What's Changed

* Replace string matching with AST parsing for `config/health.php` validation via the new `HealthConfigVisitor`, verifying the `JsonFileHealthResultStore` uses the `s3_health` disk and `health.json` path and that notifications are disabled, plus the `s3_health` filesystem disk.

## [1.2.19](https://github.com/limenet/laravel-baseline/compare/1.2.18...1.2.19) (2026-03-21)

### What's Changed

* Add `PhpVersionCheck` and `LaravelVersionCheck` Spatie health checks that report supported/recommended versions (PHP 8.4+ and Laravel 12+ pass, 8.3/11 warn, older fail), and require both to be registered in `Health::checks()`.
* Parse `app/Providers/AppServiceProvider.php` via AST (`HealthChecksStaticCallVisitor`) instead of string matching to detect registered health checks.

## [1.2.18](https://github.com/limenet/laravel-baseline/compare/1.2.17...1.2.18) (2026-03-18)

### What's Changed

* Require a scheduled `horizon:snapshot` command (in addition to the existing `horizon:terminate` post-deploy script) in `UsesLaravelHorizonCheck`.
* Allow Laravel 13 and add it to the test matrix.

## [1.2.17](https://github.com/limenet/laravel-baseline/compare/1.2.16...1.2.17) (2026-03-16)

### What's Changed

* Require health notifications to be disabled (`'enabled' => false`) in `config/health.php` as part of `UsesSpatieHealthCheck`.

## [1.2.16](https://github.com/limenet/laravel-baseline/compare/1.2.15...1.2.16) (2026-03-12)

### What's Changed

* Rewrite the docblock-removal Rector rules to strip only the matching lines from a docblock and preserve remaining PHPDoc, instead of deleting the whole comment.

## [1.2.15](https://github.com/limenet/laravel-baseline/compare/1.2.14...1.2.15) (2026-03-11)

### What's Changed

* Make `UsesSpatieHealthCheck` and `UsesSpatieBackupCheck` mandatory: a missing `spatie/laravel-health`, `spatie/cpu-load-health-check`, or `spatie/laravel-backup` package now fails (`FAIL`) with an actionable comment instead of merely warning.

## [1.2.14](https://github.com/limenet/laravel-baseline/compare/1.2.13...1.2.14) (2026-03-07)

### What's Changed

* Add backup config validations (guarded behind `spatie/laravel-backup` `^10`): require `backup.database_dump_file_timestamp_format` to be `'Y-m-d-H-i-s'` and `backup.source.files.exclude` to include `base_path('.git')`, `base_path('vendor')`, `base_path('node_modules')`, and `storage_path('framework')`.

## [1.2.13](https://github.com/limenet/laravel-baseline/compare/1.2.12...1.2.13) (2026-03-07)

### What's Changed

* Add a `verify_backup` validation requiring `verify_backup` to be `true` in `config/backup.php`, applied only when `spatie/laravel-backup` `^10` is installed.
* Expand `UsesSpatieHealthCheck` to validate the full setup: scheduled `health:check` and `health:schedule-check-heartbeat` commands, registered `Health::checks()`, the `s3_health` disk, and the `JsonFileHealthResultStore` configuration.

## [1.2.12](https://github.com/limenet/laravel-baseline/compare/1.2.11...1.2.12) (2026-03-05)

### What's Changed

* Fix the `remove-default-docblocks` Rector config to register rules via `$config->rules()` so the set works correctly, and add `config` to the PHPStan analysis paths.

## [1.2.11](https://github.com/limenet/laravel-baseline/compare/1.2.10...1.2.11) (2026-03-01)

### What's Changed

* Add a packaged Rector rule set (`LaravelBaselineSetList`) that strips default doc blocks from generated classes — factories, form requests, jobs, listeners, mailables, migrations, notifications, observers, policies, and seeders.
* Update `HasCompleteRectorConfigurationCheck` to require the new `LaravelBaselineSetList` set via `withSets(...)` instead of listing `RemoveMigrationDocBlocksRector` directly in `withRules(...)`.

## [1.2.10](https://github.com/limenet/laravel-baseline/compare/v1.2.9...1.2.10) (2026-02-27)

### What's Changed

* Raise the minimum PHP requirement to `^8.3` and drop Laravel 11 support (now requires `illuminate/contracts` / `laravel/framework` `^12`).
* Bump minimum versions of runtime and dev dependencies (`nikic/php-parser`, `phpstan/phpstan`, `spatie/laravel-package-tools`, `symfony/finder`, `symfony/yaml`, `larastan/larastan`, `pestphp/pest`, and others) and simplify the test matrix.

## [1.2.9](https://github.com/limenet/laravel-baseline/compare/v1.2.8...v1.2.9) (2026-02-27)

### What's Changed

* Add a `RemoveMigrationDocBlocksRector` Rector rule and require it in `HasCompleteRectorConfigurationCheck`, so projects strip the boilerplate doc blocks from generated migration classes.

## [1.2.8](https://github.com/limenet/laravel-baseline/compare/v1.2.7...v1.2.8) (2026-02-16)

### What's Changed

* Extend `UsesLaravelLangCheck` to also require a `./vendor/bin/pint --dirty` entry in the `post-update-cmd` scripts.

## [1.2.7](https://github.com/limenet/laravel-baseline/compare/v1.2.6...v1.2.7) (2026-02-16)

### What's Changed

* Add `UsesLaravelLangCheck`, which requires `laravel-lang/lang` as a dev dependency and a `php artisan lang:update` entry in the `post-update-cmd` scripts.

## [1.2.6](https://github.com/limenet/laravel-baseline/compare/v1.2.5...v1.2.6) (2026-02-12)

### What's Changed

* Add `DdevHasRedisAddonCheck`, which verifies the DDEV Redis add-on is installed and at least version `2.2.0` (read from `.ddev/addon-metadata/redis/manifest.yaml`).
* Make checks that read `.gitlab-ci.yml` (`HasCiJobsCheck`, `CallsSentryHookCheck`, `PhpVersionMatchesCiCheck`) fail gracefully instead of throwing when the file is missing or invalid.
* Expand test coverage across Adminer, backup, logging, and CI checks.

## [1.2.5](https://github.com/limenet/laravel-baseline/compare/v1.2.4...v1.2.5) (2026-02-06)

### What's Changed

* Add Laravel Boost v2 support to `UsesLaravelBoostCheck`: detect `laravel/boost ^2.0` and validate the new `boost.json` format (required `agents`, plus `guidelines: true` and `mcp: true`), while keeping the v1 validation path.
* Improve check command output readability with spacing and a hint showing how to exclude a check via the `baseline.excludes` config.

## [1.2.4](https://github.com/limenet/laravel-baseline/compare/v1.2.3...v1.2.4) (2026-02-02)

### What's Changed

* Add `DoesNotUseGreaterThanOrEqualConstraintsCheck`, which fails when any `require`/`require-dev` entry in `composer.json` uses a `>=` version constraint, recommending `^` instead.

## [1.2.3](https://github.com/limenet/laravel-baseline/compare/v1.2.2...v1.2.3) (2026-02-01)

### What's Changed

* Expand `HasDailyLoggingCheck` to accept more valid configurations: a direct `daily` channel, `env('LOG_CHANNEL', 'daily')`, or a `stack`/`env('LOG_CHANNEL', 'stack')` default whose stack channels include `daily`. The config is now parsed via an AST visitor to resolve `env()` calls.

## [1.2.2](https://github.com/limenet/laravel-baseline/compare/v1.2.1...v1.2.2) (2026-02-01)

### What's Changed

* Add `HasDailyLoggingCheck`, which requires `config/logging.php` to set the default log channel to `daily`.

## [1.2.1](https://github.com/limenet/laravel-baseline/compare/v1.2.0...v1.2.1) (2026-01-31)

### What's Changed

* Add `UsesLaravelAdminerCheck`, which (when `onecentlin/laravel-adminer` is installed) requires `wnx/laravel-tfa-confirmation` and validates the Adminer config and kernel middleware setup.
* Add `DoesNotUseHorizonWatcherCheck`, which fails if `spatie/laravel-horizon-watcher` is installed, since that functionality is now part of `laravel/horizon`.

## [1.2.0](https://github.com/limenet/laravel-baseline/compare/v1.1.12...v1.2.0) (2026-01-22)

### What's Changed

* Refactor the entire check suite from the monolithic `Checker` class to a one-class-per-check architecture under `src/Checks/Checks/`, with a `CheckRegistry`, `AbstractCheck` base, and per-check comment collection.
* Add `HasClaudeSettingsWithLaravelSimplifierCheck`, which validates that `.claude/settings.json` enables the `laravel-simplifier@laravel` plugin.
* Expand the Spatie backup validation (`UsesSpatieBackupCheck` / `BackupConfigValidator`) to check database dump configuration and resolve connection names through `env()` calls.

## [1.1.12](https://github.com/limenet/laravel-baseline/compare/v1.1.11...v1.1.12) (2026-01-16)

### What's Changed

* Validate that Spatie Backup's `backup.source.databases` matches the actual `default` connection parsed from `config/database.php` (instead of requiring a literal `config('database.default')` call), and fail with a clear message when `config/database.php` is missing.
* Accept either `env('APP_NAME', ...)` or `env('APP_URL', ...)` for `backup.name`, and require `monitor_backups[*].name` to use the same env variable and default as `backup.name`.

## [1.1.11](https://github.com/limenet/laravel-baseline/compare/v1.1.10...v1.1.11) (2026-01-16)

### What's Changed

* Revert the previous release's requirement that `composer bump` also appear in `post-require-cmd`; `bumpsComposer()` again only checks `post-update-cmd`.

## [1.1.10](https://github.com/limenet/laravel-baseline/compare/v1.1.9...v1.1.10) (2026-01-16)

### What's Changed

* Require `composer bump` in the `post-require-cmd` composer script in addition to `post-update-cmd` for the `bumpsComposer()` check (reverted in v1.1.11).

## [1.1.9](https://github.com/limenet/laravel-baseline/compare/v1.1.8...v1.1.9) (2026-01-16)

### What's Changed

* Add comprehensive Spatie Backup config validation via a new `src/Backup/` parser (`BackupConfigValidator`/`BackupConfigVisitor`) covering backup name, monitor names, disks, file source settings, database source, mail notifications, and cleanup retention settings.
* Lower the expected cleanup retention values to `keep_all_backups_for_days=7` and `keep_daily_backups_for_days=16`.

## [1.1.8](https://github.com/limenet/laravel-baseline/compare/v1.1.7...v1.1.8) (2026-01-14)

### What's Changed

* Add `phpstanLevelAtLeastEight()` check requiring `phpstan.neon` to set `parameters.level` to at least 8 (or `max`).
* Fail the DDEV mutagen check when `.ddev/.gitignore` ignores itself (lists `/.gitignore` or `.gitignore`).
* Update CI tooling: bump PHPStan-checking and codecov configuration, plus `actions/checkout` 5→6 and `dependabot/fetch-metadata` 2.4.0→2.5.0.

## [1.1.7](https://github.com/limenet/laravel-baseline/compare/v1.1.6...v1.1.7) (2026-01-06)

### What's Changed

* Fail the DDEV mutagen check when `.ddev/.gitignore` still contains the `#ddev-generated` marker, prompting it to be removed so the file is tracked.

## [1.1.6](https://github.com/limenet/laravel-baseline/compare/v1.1.5...v1.1.6) (2026-01-06)

### What's Changed

* Fail the DDEV mutagen check when `.ddev/mutagen/mutagen.yml` contains the `#ddev-generated` marker, so it is not silently overwritten by DDEV.

## [1.1.5](https://github.com/limenet/laravel-baseline/compare/v1.1.4...v1.1.5) (2026-01-06)

### What's Changed

* Extend the DDEV mutagen check to fail when `.ddev/.gitignore` ignores `mutagen/mutagen.yml`, ensuring the config stays tracked in git.

## [1.1.4](https://github.com/limenet/laravel-baseline/compare/v1.1.3...v1.1.4) (2026-01-06)

### What's Changed

* Add `ddevMutagenIgnoresNodeModules()` check requiring `.ddev/mutagen/mutagen.yml` to list `/node_modules` under `sync.defaults.ignore.paths`.
* Add PHP 8.5 to the test matrix (excluding the PHP 8.5 + Laravel 11 combination) and run PHPStan CI on PHP 8.5.

## [1.1.3](https://github.com/limenet/laravel-baseline/compare/v1.1.2...v1.1.3) (2025-12-28)

### What's Changed

* Relax the GitLab CI `hasCiJobs()` check to allow extra keys (e.g. `before_script`, `variables`, `artifacts`) alongside `extends` in each job definition instead of requiring an exact match.

## [1.1.2](https://github.com/limenet/laravel-baseline/compare/v1.1.1...v1.1.2) (2025-12-09)

### What's Changed

* Stop requiring `FunctionLikeToFirstClassCallableRector` in the Rector `withSkip()` config since that rule has been deprecated.

## [1.1.1](https://github.com/limenet/laravel-baseline/compare/v1.1.0...v1.1.1) (2025-11-14)

### What's Changed

* Validate Laravel Boost configuration by requiring a `boost.json` with the `claude_code` and `phpstorm` agents and the `claude_code`, `phpstorm`, and `vscode` editors.

## [1.1.0](https://github.com/limenet/laravel-baseline/compare/v1.0.0...v1.1.0) (2025-11-14)

### What's Changed

* Add a Rector check (`RectorVisitorPaths`) that verifies `rector.php` calls `withPaths()` with the `app`, `database`, `routes`, and `tests` directories.
* Upgrade several previously-optional checks to hard failures: `usesLaravelBoost`, `usesLaravelPulse`, `usesPredis`, `usesLaravelHorizon`, and `usesRector` now `FAIL` instead of `WARN` when their packages are absent or misconfigured; `usesLaravelPennant` remains a warning.
* Refactor package-presence checks to use `checkComposerPackages` directly, removing the `checkPackagePresence` helper (internal cleanup, no behavior change).

## [1.0.0](https://github.com/limenet/laravel-baseline/compare/v0.2.12...v1.0.0) (2025-11-07)

### What's Changed

* Add AI-guidelines support: a new `limenet:laravel-baseline:guidelines` artisan command that publishes the package's `baseline-*.md` files into `.ai/guidelines`, plus a `hasGuidelinesUpdateScript` check requiring that command in `composer.json` `post-update-cmd`.
* Add a check enforcing that the `limenet:laravel-baseline:guidelines` script runs before `boost:update` in `post-update-cmd`.
* Change the `callsBaseline` check to require the full `limenet:laravel-baseline:check` command (previously matched the looser `limenet:laravel-baseline`).
* Modernize dev dependencies to Pest 4 (with Pest 3 back-compat) and raise minimum versions of Larastan, Laravel, Pint, and PHPStan tooling.
* Add CI test coverage reporting via pcov and a Codecov badge; expand the test suite for broader coverage (internal).

## [0.2.12](https://github.com/limenet/laravel-baseline/compare/v0.2.11...v0.2.12) (2025-10-31)

### What's Changed

* Add a `doesNotUseSail` check that fails if `laravel/sail` is installed or a `docker-compose.yml` exists in the project root.
* Improve Rector check error messages: each Rector visitor now produces a specific, actionable message describing the missing or incorrect `rector.php` call.

## [0.2.11](https://github.com/limenet/laravel-baseline/compare/v0.2.10...v0.2.11) (2025-10-31)

### What's Changed

* Strengthen the `usesLaravelBoost` check to also require a `boost:update` entry in `composer.json` `post-update-cmd`, failing if the script is missing.

## [0.2.10](https://github.com/limenet/laravel-baseline/compare/v0.2.9...v0.2.10) (2025-10-27)

### What's Changed

* Register the `hasNpmScripts` and `usesReleaseIt` checks in the command runner so they actually execute (they were defined but not wired in).

## [0.2.9](https://github.com/limenet/laravel-baseline/compare/v0.2.8...v0.2.9) (2025-10-27)

### What's Changed

* Add a `hasNpmScripts` check requiring `ci-lint` and `production` scripts in `package.json`.
* Add a `usesReleaseIt` check requiring `release-it` and `@release-it/bumper` dev dependencies, a `release` script, and a `.release-it.json` bumper config pointing at `composer.json`/`version`.
* Fold the Rector `ci-lint` script requirement into the `usesRector` check and remove it from `isCiLintComplete`.

## [0.2.8](https://github.com/limenet/laravel-baseline/compare/v0.2.7...v0.2.8) (2025-10-26)

### What's Changed

* Require `phpstan/extension-installer` (in addition to the deprecation- and strict-rules packages) in the `usesPhpstanExtensions` check.
* Move the PHP Insights `ci-lint` script requirements out of `isCiLintComplete` and into the `usesPhpInsights` check so the two are validated independently.

## [0.2.7](https://github.com/limenet/laravel-baseline/compare/v0.2.6...v0.2.7) (2025-10-24)

### What's Changed

* Add a `phpVersionMatchesCi` check ensuring the PHP version in `composer.json` matches `PHP_VERSION` in `.gitlab-ci.yml`.
* Add a `ddevHasPcovPackage` check verifying `.ddev/config.yaml` lists `php${DDEV_PHP_VERSION}-pcov` under `webimage_extra_packages`.
* Refactor the checker internals and improve several error messages (no behavior change).

## [0.2.6](https://github.com/limenet/laravel-baseline/compare/v0.2.5...v0.2.6) (2025-10-23)

### What's Changed

* Require a `withAttributesSets()` call in the Rector configuration check.

## [0.2.5](https://github.com/limenet/laravel-baseline/compare/v0.2.4...v0.2.5) (2025-10-22)

### What's Changed

* Require `rector.php` to skip `FunctionLikeToFirstClassCallableRector` via `withSkip()`.

## [0.2.4](https://github.com/limenet/laravel-baseline/compare/v0.2.3...v0.2.4) (2025-10-22)

### What's Changed

* Drop `strictBooleans` from the required Rector `withPreparedSets()` set.

## [0.2.3](https://github.com/limenet/laravel-baseline/compare/v0.2.2...v0.2.3) (2025-10-17)

### What's Changed

* Extend the Pulse and Telescope checks to verify `PULSE_ENABLED`/`TELESCOPE_ENABLED` are set to `false` in `phpunit.xml` (via a new `checkPhpunitEnvVar` helper).
* Add a coverage `<source>` configuration check requiring `phpunit.xml` to include `./app` with a `.php` suffix.
* Bump `stefanzweifel/git-auto-commit-action` from 6 to 7 in CI workflows.

## [0.2.2](https://github.com/limenet/laravel-baseline/compare/v0.2.1...v0.2.2) (2025-10-14)

### What's Changed

* Improve Rector check reporting: failures now emit a specific comment naming which visitor did not find the expected call (e.g. `withImportNames`/`withRules`), instead of failing silently.

## [0.2.1](https://github.com/limenet/laravel-baseline/compare/v0.2.0...v0.2.1) (2025-10-12)

### What's Changed

* Extend the Rector configuration check to require `->withImportNames(importShortClasses: false)` and `->withRules([AddGenericReturnTypeToRelationsRector::class])` in `rector.php`, enforcing generic return types on Eloquent relations.
* Add a new `RectorVisitorArrayArgument` visitor and teach `RectorVisitorNamedArgument` to assert negated (`false`) named arguments via a `!` prefix.

## [0.2.0](https://github.com/limenet/laravel-baseline/compare/v0.1.19...v0.2.0) (2025-09-28)

### What's Changed

* Drop support for Laravel 10; the package now requires `illuminate/contracts` `^11.0||^12.0` and `laravel/framework` `^11.0||^12.0`.
* Bump minimum dependency floors: `phpstan/phpstan` to `^2.1.8`, Pest and its plugins to `^3.0||^4.0`, and the PHPStan PHPUnit/deprecation rule packages to `^2.0`.
* Refactor all check logic out of `LaravelBaselineCommand` into a dedicated `Checker` service backed by a new test suite (`CheckerTest`), and simplify comment/error handling.
* Add PHPStan unit tests with fixtures for the `EnforceWithoutRelationsOnJobsRule` rule.
* Adopt `limenet/laravel-pint-config` for code style and add a `pint.json`.
* Harden CI: disable `fail-fast` and add a `prefer-lowest` stability matrix to catch minimum-version issues.

## [0.1.19](https://github.com/limenet/laravel-baseline/compare/v0.1.18...v0.1.19) (2025-09-23)

### What's Changed

* Add a `usesLaravelPulse` check that warns when `laravel/pulse` is absent and fails unless `pulse:trim` is scheduled.
* Extend the Telescope check to also require a scheduled `telescope:prune` command.
* Strengthen the Spatie Health and Backup checks to require their scheduled commands (`health:check` + `health:schedule-check-heartbeat`, and `backup:run` + `backup:clean`) rather than passing on package presence alone.
* Add a `hasScheduleEntry` helper that inspects registered `Schedule` events.

## [0.1.18](https://github.com/limenet/laravel-baseline/compare/v0.1.17...v0.1.18) (2025-09-21)

### What's Changed

* Fix an undefined-array-key error in the GitLab CI job check by guarding missing job entries with a null coalesce.
* Relax the PHPInsights CI-lint matching to look for `insights ...` without requiring the `artisan`/`php artisan` prefix.

## [0.1.17](https://github.com/limenet/laravel-baseline/compare/v0.1.16...v0.1.17) (2025-09-21)

### What's Changed

* Move `laravel/framework` to `require-dev` and depend only on `illuminate/contracts` in production, reducing the package's runtime dependency footprint.

## [0.1.16](https://github.com/limenet/laravel-baseline/compare/v0.1.15...v0.1.16) (2025-09-12)

### What's Changed

* Update the CI-lint check to require Pint to run with `--parallel`.

## [0.1.15](https://github.com/limenet/laravel-baseline/compare/v0.1.14...v0.1.15) (2025-09-12)

### What's Changed

* Update the CI-lint check to require the `insights --summary` flag on the PHPInsights run.

## [0.1.14](https://github.com/limenet/laravel-baseline/compare/v0.1.13...v0.1.14) (2025-09-05)

### What's Changed

* Fix the Sentry release-hook check to safely handle a missing `release.extends` entry in `.gitlab-ci.yml` instead of erroring.

## [0.1.13](https://github.com/limenet/laravel-baseline/compare/v0.1.12...v0.1.13) (2025-09-05)

### What's Changed

* Add a PHPStan rule, `EnforceWithoutRelationsOnJobsRule`, that flags queued job (`ShouldQueue`) constructor parameters typed as Eloquent models unless they carry the `#[WithoutRelations]` attribute (class- or parameter-level).
* Allow Pest v4 by widening `pestphp/pest` and its arch/Laravel plugins to `^4.0`.

## [0.1.12](https://github.com/limenet/laravel-baseline/compare/v0.1.11...v0.1.12) (2025-09-05)

### What's Changed

* Fix the `phpunit.xml` APP_KEY check to read from `<php><env>` entries instead of `<php><server>`.

## [0.1.11](https://github.com/limenet/laravel-baseline/compare/v0.1.10...v0.1.11) (2025-09-05)

### What's Changed

* Add a `checkPhpunit` check validating `phpunit.xml`, requiring a Cobertura coverage report writing to `cobertura.xml` and a base64 `APP_KEY` env var.
* Add a `hasCiJobs` check requiring the `.gitlab-ci.yml` `build`, `php`, `js`, and `test` jobs to extend `.build`, `.lint_php`, `.lint_js`, and `.test` respectively.
* Add a `callsSentryHook` check that verifies a correctly configured Sentry release webhook in `.gitlab-ci.yml` when `sentry/sentry-laravel` is installed.
* Add a `usesPhpInsights` check (requires `nunomaduro/phpinsights`) and extend the CI-lint check to require the PHPInsights and CodeClimate report commands.
* Update package metadata/defaults (rename README, set a real description).

## [0.1.10](https://github.com/limenet/laravel-baseline/compare/v0.1.9...v0.1.10) (2025-08-23)

### What's Changed

* Adjust spacing/newlines in `LaravelBaselineCommand` output; no behavior change.

## [0.1.9](https://github.com/limenet/laravel-baseline/compare/v0.1.8...v0.1.9) (2025-08-23)

### What's Changed

* Improve non-verbose output of `limenet:laravel-baseline`: prints an explicit success message on pass and an error summary with a hint to run `-v`/`-vv` on failure.
* Gate the per-check "Rector check" diagnostic line in `AbstractRectorVisitor` behind very-verbose output, and fix the verbosity call to use `$this->command->getOutput()`.

## [0.1.8](https://github.com/limenet/laravel-baseline/compare/v0.1.7...v0.1.8) (2025-08-23)

### What's Changed

* Rework command output verbosity: the full per-check result list now shows only with `-v`, errors-only with `-q`, and the "Composer check"/"Composer script check" diagnostics only with `-vv`.
* Add `phpstan.neon` and `phpunit.xml` config files to the package.

## [0.1.7](https://github.com/limenet/laravel-baseline/compare/v0.1.6...v0.1.7) (2025-08-22)

### What's Changed

* Add an `excludes` config key in `config/baseline.php`: checks listed there are skipped and reported as excluded instead of run.

## [0.1.6](https://github.com/limenet/laravel-baseline/compare/v0.1.5...v0.1.6) (2025-08-22)

### What's Changed

* Lower the minimum PHP requirement from `^8.3` to `^8.2`.

## [0.1.5](https://github.com/limenet/laravel-baseline/compare/v0.1.4...v0.1.5) (2025-08-22)

### What's Changed

* Add the `callsBaseline` check, which fails unless `limenet:laravel-baseline` is wired into the `post-update-cmd` scripts.

## [0.1.4](https://github.com/limenet/laravel-baseline/compare/v0.1.3...v0.1.4) (2025-08-22)

### What's Changed

* Strengthen the `usesLimenetPintConfig` check to also require the `laravel-pint-config:publish` post-update script, not just the `limenet/laravel-pint-config` package.

## [0.1.3](https://github.com/limenet/laravel-baseline/compare/v0.1.2...v0.1.3) (2025-08-22)

### What's Changed

* Downgrade the `usesLaravelBoost` check from `FAIL` to `WARN` when `laravel/boost` is absent.

## [0.1.2](https://github.com/limenet/laravel-baseline/compare/v0.1.1...v0.1.2) (2025-08-22)

### What's Changed

* Fix the inverted `doesNotUseIgnition` check so it now correctly fails when `spatie/laravel-ignition` is installed and passes when it is absent.

## [0.1.1](https://github.com/limenet/laravel-baseline/compare/v0.1.0...v0.1.1) (2025-08-22)

### What's Changed

* Add the `bumpsComposer` check, which requires a `composer bump` entry in the `post-update-cmd` scripts.
* Add the `doesNotUseIgnition` check flagging the presence of `spatie/laravel-ignition`.
* Strengthen the `usesLaravelTelescope` check to also require the `telescope:publish` post-update script.
* Trim the `README.md`.

## [0.1.0](https://github.com/limenet/laravel-baseline/commits/v0.1.0) (2025-08-22)

### What's Changed

* Ship the initial package with the `limenet:laravel-baseline` command that runs a suite of convention checks against a Laravel project, each reporting `PASS`/`FAIL`/`WARN` with an icon, and exiting non-zero on any failure.
* Include the first set of checks covering Rector configuration, encrypted env file, CI lint completeness, maintained Laravel version, IDE helpers, Larastan, Laravel Boost, Horizon, Pennant, Telescope, `limenet/laravel-pint-config`, Pest, PHPStan extensions, Predis, Rector, and Spatie Backup/Health.
* Add Rector AST visitor helpers (`AbstractRectorVisitor`, `RectorVisitorClassFetch`, `RectorVisitorHasCall`, `RectorVisitorNamedArgument`) for parsing Rector config, plus the `CheckResult` enum, facade, service provider, and a publishable `config/baseline.php`.
