# Laravel Baseline

[![Latest Version on Packagist](https://img.shields.io/packagist/v/limenet/laravel-baseline.svg?style=flat-square)](https://packagist.org/packages/limenet/laravel-baseline)
[![run-tests](https://github.com/limenet/laravel-baseline/actions/workflows/run-tests.yml/badge.svg)](https://github.com/limenet/laravel-baseline/actions/workflows/run-tests.yml)
[![Fix PHP code style issues](https://github.com/limenet/laravel-baseline/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/limenet/laravel-baseline/actions/workflows/fix-php-code-style-issues.yml)
[![codecov](https://codecov.io/gh/limenet/laravel-baseline/graph/badge.svg?token=Q57FG1L28A)](https://codecov.io/gh/limenet/laravel-baseline)
[![Total Downloads](https://img.shields.io/packagist/dt/limenet/laravel-baseline.svg?style=flat-square)](https://packagist.org/packages/limenet/laravel-baseline)

Checks your Laravel installation against a highly opinionated baseline.

This repository ships **two runners** from one policy:

| | Composer | npm |
| --- | --- | --- |
| Package | `limenet/laravel-baseline` | `@limenet-ch/baseline` |
| For | Laravel projects (DDEV, composer) | JS/TS-only projects (no PHP, no DDEV) |
| Command | `php artisan limenet:laravel-baseline:check` | `npx baseline check` |
| Checks | all of them | [the portable subset](#js-only-projects) |

Both read `policy/`, so the version floors and required keys are defined once, and both are
executed against the shared behavioural fixtures in `fixtures/`. They are released in lockstep:
the same version number means the same policy in both ecosystems.


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

### AI guidelines & skills

The package also ships [Laravel Boost](https://laravel.com/docs/boost) resources under
`resources/boost/`: an always-on guideline (the dev loop — `ci-lint`, tests, DDEV-first
conventions) and on-demand skills (e.g. `creating-a-release`). When a project that has
`laravel/boost` installed runs `php artisan boost:install` or `php artisan boost:update --discover`,
Boost discovers and publishes these to the consuming project's coding agents automatically.

## JS-only projects

Projects with no PHP and no DDEV use the npm runner instead. It is a second implementation, not a
wrapper: PHP cannot reach into a JS project (and by this baseline's own convention `npm` runs on
the host while artisan runs inside DDEV), so the portable checks are reimplemented in TypeScript and
kept honest by the shared fixtures rather than by shared code.

```bash
npm install --save-dev @limenet-ch/baseline
```

```bash
npx baseline check              # report issues
npx baseline check --fix        # apply safe fixes, then report what is left
npx baseline periodic           # walk through expired periodic checks
npx baseline install-skills     # copy the packaged skills into .claude/skills/
```

Wire it into the `ci-lint` npm script so CI and the Claude Stop hook both run it — npm has no
`post-update-cmd` equivalent, and npm 12 blocks dependency lifecycle scripts by default:

```json
"scripts": {
    "ci-lint": "biome ci . && tsc --noEmit && baseline check"
}
```

State lives in `.baseline.json` at the project root (a JS project has no `config/` directory):

```json
{
    "excludes": ["hasNpmScripts"],
    "periodic": { "updatesDependencies": "2026-08-16T09:00:00.000Z" }
}
```

### What the npm runner checks

| Check | Relationship to the Laravel runner |
| --- | --- |
| `nodeVersion` | identical |
| `hardensNpmSupplyChain` | identical |
| `hasEditorconfig` | identical |
| `biomeUsesLocalSchema` | identical |
| `doesNotHaveCopilotOrJunieAgentFiles` | identical |
| `doesNotUseBothBaselineRunners` | mirrored: fails when `composer.json` requires `limenet/laravel-baseline`, since the Composer runner wins |
| `allowsToolingInClaudeSettings` | requires only the shared allow entries, not the DDEV/artisan ones |
| `deniesEnvReadsInClaudeSettings` | identical |
| `runsCiLintHookInClaudeSettings` | hooks `npm run ci-lint` instead of `ddev composer run ci-lint` |
| `updatesDependencies` | identical (periodic, every 30 days) |
| `hasNpmScripts` | identical |
| `hasCiJobs` | same GitLab CI templates, without the `php` job |
| `hasTrivyConfig` | identical, canonical config included: its `vendor/**`, `storage/logs/` and `.ddev/` skips are inert in a JS project |
| `ciSetsNodeVersion` | **npm-only**: the Laravel runner does not register it |
| `isCiLintComplete` | asserts the JS toolchain in the npm script, not pint/phpstan in a composer script |
| `callsBaseline` | hooks the `ci-lint` npm script, since npm has no `post-update-cmd` |
| `usesReleaseIt` | **inverted**: fails if `@release-it/bumper` is configured, because `package.json` is already release-it's source of truth |

Deliberately not ported: everything composer-, artisan-, Rector-, PHPStan- or Spatie-Health-shaped;
the DDEV checks (`ddevNodeVersionIsAuto`, `ddevMutagenIgnoresNodeModules`, …); and
`hasClaudeSettingsWithLaravelSkills` / `doesNotHaveLaravelSimplifierInClaudeSettings`, which are
vacuous without Laravel.

## Checks

This package validates your Laravel installation against the following checks:

### Testing & Quality Tools
- **`usesPest()`** - Validates Pest testing framework is configured (not PHPUnit directly)
- **`usesPestPhpstanPlugin()`** - Validates `pestphp/pest-plugin-phpstan` is installed when Pest 5+ and PHPStan are both present (warns if not applicable)
- **`usesPestRectorPlugin()`** - Validates `pestphp/pest-plugin-rector` is installed when Pest 5+ and Rector are both present (warns if not applicable)
- 🔧 **`usesRector()`** - Validates Rector automated code modernization is installed *(partial: fixes ci-lint script if packages installed)*
- **`usesLarastan()`** - Validates Larastan static analysis tool is configured
- **`usesPhpstanExtensions()`** - Validates PHPStan extensions are installed
- **`phpstanLevelAtLeastEight()`** - Validates PHPStan is configured to at least level 8
- 🔧 **`phpstanParsesModelCastsMethod()`** - Validates `phpstan.neon` sets `parseModelCastsMethod: true`: `ModelCastsPropertyToCastsMethodRector` rewrites `protected $casts = [...]` into a `casts(): array` method, and without this parameter Larastan reads only the generated `@return array<string, string>` — not a constant array — so every cast is lost and datetime attributes report as strings *(inserts the parameter into the `parameters` block)*
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
- 🔧 **`hasRectorConfigWithPestSet()`** - Validates Rector `withSets([PestSetList::CODING_STYLE])` is configured when Pest 5+ and Rector are both present *(appends call to rector.php; warns if not applicable)*
- 🔧 **`hasRectorConfigWithSkip()`** - Validates Rector `withSkip()` contains required skipped rules (always: 6 Laravel rules; Laravel 13+: TablePropertyToTableAttributeRector; when server.php exists: ServerVariableToRequestFacadeRector) *(appends call to rector.php)*

### IDE & Developer Tools
- 🔧 **`hasEditorconfig()`** - Validates `.editorconfig` exists with required settings (`root = true`, `charset`, `end_of_line`, `indent_style`, `insert_final_newline`, `trim_trailing_whitespace`) *(creates `.editorconfig` with canonical content if missing or incomplete)*
- 🔧 **`hasClaudeSettingsWithLaravelSkills()`** - Validates Claude Code settings include the Laravel agent skills plugin and marketplace *(creates/merges `.claude/settings.json`)*
- 🔧 **`doesNotHaveLaravelSimplifierInClaudeSettings()`** - Fails if the deprecated `laravel-simplifier@laravel` plugin is still enabled in `.claude/settings.json` *(removes the entry)*
- 🔧 **`deniesEnvReadsInClaudeSettings()`** - Validates `.claude/settings.json` `permissions.deny` blocks reading `.env` plus every environment that ships an encrypted file (each `.env.{env}.encrypted` in the project root requires denying `.env.{env}`); `.env.example` stays readable *(merges the deny entries)*
- 🔧 **`allowsToolingInClaudeSettings()`** - Validates `.claude/settings.json` `permissions.allow` includes the DDEV dev-loop commands (`ddev composer run ci-lint`, `ddev composer test`, and safe artisan commands: `test`, `make:*`, `route:list`, `about`, `config:show`, `ide-helper`, `optimize:clear`, `cache:clear`, `config:clear`, `route:clear`, `view:clear`) so the dev loop runs without prompts *(merges the allow entries)*
- 🔧 **`asksBeforeDestructiveDbCommandsInClaudeSettings()`** - Validates `.claude/settings.json` `permissions.ask` requires a confirmation before the artisan commands that destroy database contents (`migrate:fresh`, `migrate:refresh`, `migrate:reset`, `migrate:rollback`, `db:wipe`), covering both the `ddev artisan` and `php artisan` forms; `ask` rather than `deny` so the developer keeps an approval path *(merges the ask entries)*
- 🔧 **`runsCiLintHookInClaudeSettings()`** - Validates `.claude/settings.json` has a `Stop` hook running `ddev composer run ci-lint` *(appends the hook)*
- 🔧 **`usesIdeHelpers()`** - Validates Laravel IDE Helper is configured: `post-update-cmd` runs `ide-helper:generate`, `ide-helper:models`, and `ide-helper:meta`, and `.gitignore` ignores the generated `_ide_helper.php`, `_ide_helper_models.php`, and `.phpstorm.meta.php` files *(partial: adds post-update scripts and gitignore entries if package installed)*
- 🔧 **`gitignoresLspFiles()`** - Validates `.gitignore` ignores `storage/framework/lsp-*.php`, the per-editor files the Laravel language server writes into `storage/framework` *(appends the entry, creating `.gitignore` if missing)*
- **`usesLaravelAdminer()`** - Warns if Laravel Adminer database UI is missing (optional), validates TFA confirmation and configuration when installed
- 🔧 **`usesLaravelBoost()`** - Validates Laravel Boost AI development tool *(partial: fixes boost.json and post-update script if package installed)*
- 🔧 **`laravelBoostMcpUsesDdev()`** - Validates `.mcp.json` configures the `laravel-boost` MCP server to use `ddev artisan boost:mcp` *(creates/fixes `.mcp.json`; warns if `laravel/boost` not installed)*
- 🔧 **`doesNotHaveCopilotOrJunieAgentFiles()`** - Fails if `AGENTS.md`, a `.junie` directory, or a `.github/skills` directory exist — these are generated for the Copilot/Junie Boost agents, which are no longer required *(deletes `AGENTS.md`, the `.junie` directory, and the `.github/skills` directory)*
- **`runsBoostUpdate()`** *(periodic, every 30 days)* - Warns if Laravel Boost is not installed; when installed, fails until a developer confirms running `php artisan boost:update --discover` via `limenet:laravel-baseline:periodic`
- **`followsModernLaravelIdioms()`** *(periodic, every 30 days)* - Fails until a developer confirms running the `auditing-laravel-idioms` skill, which audits typed cache getters, BackedEnum cache/session keys, and `whenFilledEnum()` for request data
- **`updatesDependencies()`** *(periodic, every 30 days)* - Fails until a developer confirms (via `limenet:laravel-baseline:periodic`) that composer & npm dependencies were updated by running the `updating-dependencies` skill — which updates in-constraint packages, reviews changelogs for project impact, and reports semver-blocked majors
- 🔧 **`usesLimenetPintConfig()`** - Validates custom Laravel Pint formatting standards *(partial: adds post-update script if package installed)*

### Laravel Features & Monitoring
- 🔧 **`usesLaravelHorizon()`** - Validates Laravel Horizon Redis queue manager *(partial: adds ci-deploy-post script if package installed)*
- **`usesLaravelPennant()`** - Warns if Laravel Pennant feature flags are missing (optional)
- 🔧 **`usesLaravelPulse()`** - Validates Laravel Pulse application monitoring *(partial: adds PULSE_ENABLED=false to phpunit.xml if package installed)*
- 🔧 **`cacheAllowsPulseSerializableClasses()`** - On Laravel 13+ with Pulse installed, validates the top-level `serializable_classes` allow-list in config/cache.php permits the classes Pulse round-trips through the cache (`stdClass`, `Illuminate\Support\Collection`, `Carbon\CarbonImmutable`) — the Laravel 13 skeleton ships `'serializable_classes' => false`, which makes every Pulse card fail in production with "tried to access a property on an incomplete object" *(adds the missing classes when the value is `false` or an incomplete array)*
- 🔧 **`usesLaravelTelescope()`** - Validates Laravel Telescope request debugging *(partial: adds post-update script and TELESCOPE_ENABLED=false to phpunit.xml if package installed)*
- **`usesSpatieHealthSetup()`** - Validates Spatie Health packages, schedules, s3_health disk, and result store configuration
- 🔧 **`usesSpatieHealthHasCoreChecks()`** - Validates core health checks (CacheCheck, CpuLoadCheck, DatabaseCheck, DatabaseConnectionCountCheck, DebugModeCheck, EnvironmentCheck, HorizonCheck, QueueCheck, RedisCheck, ScheduleCheck, UsedDiskSpaceCheck) are registered *(adds missing checks to Health::checks() in AppServiceProvider)*
- 🔧 **`usesSpatieHealthHasLaravelVersionCheck()`** - Validates LaravelVersionCheck is registered in Health::checks() *(adds to AppServiceProvider)*
- 🔧 **`usesSpatieHealthHasPhpVersionCheck()`** - Validates PhpVersionCheck is registered in Health::checks() *(adds to AppServiceProvider)*
- 🔧 **`usesSpatieHealthHasReleaseAgeCheck()`** - Validates ReleaseAgeCheck is registered in Health::checks() *(adds to AppServiceProvider)*
- **`usesSpatieHealthCacheCheckCacheStore()`** - Validates CacheCheck uses the dedicated 'health-checks' cache store via `->driver('health-checks')` in AppServiceProvider and config/cache.php
- **`usesSpatieHealthQueueCheckCacheStore()`** - Validates QueueCheck: DispatchQueueCheckJobsCommand is scheduled everyMinute(), uses the dedicated 'health-checks' file cache store in AppServiceProvider and config/cache.php
- **`usesSpatieHealthQueueCheckHorizonQueues()`** - Validates QueueCheck registers all queues from config/horizon.php via onQueue() (requires laravel/horizon)
- **`usesSpatieHealthScheduleCheckCacheStore()`** - Validates ScheduleCheck uses the dedicated 'health-checks' cache store in both AppServiceProvider and config/cache.php
- 🔧 **`usesSpatieHealthScheduleCheckHeartbeat()`** - Validates ScheduleCheck is configured with `heartbeatMaxAgeInMinutes(2)` to prevent false positives *(appends the call to ScheduleCheck in AppServiceProvider)*
- **`usesSpatieBackup()`** - Validates Spatie Backup database backups with comprehensive config validation

### Infrastructure & Dependencies
- **`usesPredis()`** - Validates Predis Redis client is installed
- **`isLaravelVersionMaintained()`** - Validates Laravel 11+ is used
- 🔧 **`doesNotUseSail()`** - Validates Sail is NOT used *(partial: deletes docker-compose.yml; run `composer remove laravel/sail` manually)*
- 🔧 **`doesNotUsePhpInsights()`** - Validates PHP Insights is NOT used *(removes the `nunomaduro/phpinsights` composer.json entry, leftover ci-lint script entries, and config/insights.php; run `composer update` afterward to sync composer.lock)*
- **`doesNotUseSpatiePasskeysWithFortify()`** - Fails if both `spatie/laravel-passkeys` and `laravel/fortify` are installed, as they overlap in authentication responsibility
- **`doesNotUseBothBaselineRunners()`** - Fails when `package.json` also declares `@limenet-ch/baseline`: the npm runner is the fallback for projects this package cannot reach, and in a Laravel project this one wins (reports the `npm uninstall` to run; never uninstalls for you)
- **`doesNotUseHorizonWatcher()`** - Validates Spatie Horizon Watcher is NOT installed
- 🔧 **`doesNotUseGreaterThanOrEqualConstraints()`** - Validates no `>=` version constraints in composer.json (use `^` or `~` instead) *(replaces `>=X.Y` with `^X.Y` in composer.json)*

### CI/CD & Deployment
- **`hasCiJobs()`** - Validates GitLab CI pipeline jobs are properly configured (the `test` job may extend either `.test` or `.test_db`)
- 🔧 **`hasTrivyConfig()`** - Validates Trivy security scanning CI job, `trivy.yaml` (scanners, skip-files, skip-dirs, ignorefile, cache.dir, telemetry/VEX/dependency-tree flags, and `pkg.include-dev-deps` so development dependencies are reported instead of silently skipped), presence of `.trivyignore.yaml`, and `.trivycache/` in `.gitignore` *(creates/merges trivy.yaml, creates an empty .trivyignore.yaml, appends to .gitignore, and adds CI job)*
- **`callsSentryHook()`** - Warns if Sentry error tracking is missing (optional)
- **`phpVersionMatchesCi()`** - Validates PHP version consistency with CI configuration
- **`isCiLintComplete()`** - Validates complete linting pipeline
- **`doesNotUseIgnition()`** - Validates Ignition debugger is NOT installed

### Local Development
- **`phpVersionMatchesDdev()`** - Validates PHP version consistency with DDEV
- 🔧 **`nodeVersion()`** - Validates the project pins Node >= 24 (the current LTS) in both `package.json` `engines.node` and `.nvmrc`, compatible with each other *(creates the missing constraint — establishing Node 24 when none is declared — and bumps a declaration that allows anything older to 24; a newer line such as Node 26 is left alone, and a conflict between existing `engines.node` and `.nvmrc` is reported, not auto-resolved)*
- 🔧 **`hardensNpmSupplyChain()`** - Hardens npm against supply-chain attacks: requires `package.json` `engines.npm` >= 12 (npm 12 blocks dependency lifecycle scripts by default and refuses git/remote deps), `.npmrc` `engine-strict=true` so that requirement is enforced rather than advisory, and `.npmrc` `min-release-age=7` for a 7-day install cooldown that skips freshly-published (potentially compromised) versions *(sets `engines.npm` to `^12`, and upserts both `.npmrc` keys while preserving existing lines)*
- 🔧 **`ddevHasPcovPackage()`** - Validates DDEV coverage configuration *(adds pcov to webimage_extra_packages and creates .ddev/php/90-custom.ini)*
- **`ddevHasRedisAddon()`** - Validates DDEV Redis addon is installed and at minimum version 2.2.0
- 🔧 **`ddevMutagenIgnoresNodeModules()`** - Validates DDEV Mutagen sync configuration *(creates mutagen.yml and fixes .gitignore)*
- 🔧 **`ddevNodeVersionIsAuto()`** - Validates `.ddev/config.yaml` sets `nodejs_version: auto` so DDEV derives the Node version from the project's `.nvmrc` instead of pinning its own *(sets `nodejs_version: auto`, preserving surrounding comments and formatting)*
- **`updatesDdevAddons()`** - Fails if any installed DDEV add-on (`.ddev/addon-metadata/*/manifest.yaml`) has an `install_date` older than 3 months; comment shows the `ddev add-on get <repository>` command to refresh each stale add-on

### Build & Release
- 🔧 **`bumpsComposer()`** - Validates automatic composer dependency bumping *(adds `composer bump` to post-update-cmd)*
- 🔧 **`usesReleaseIt()`** - Validates automated release management *(partial: creates/fixes .release-it.json and adds release npm script if packages installed)*
- **`hasNpmScripts()`** - Validates required npm build scripts
- 🔧 **`biomeUsesLocalSchema()`** - Validates that `biome.json`, when the project has one, points `$schema` at `./node_modules/@biomejs/biome/configuration_schema.json` rather than a version-pinned remote URL, so the schema follows the installed Biome instead of needing a manual bump on every update. Passes when the project does not use Biome. *(rewrites or inserts the `$schema` line as a targeted text edit, leaving the rest of the file — comments included — byte-identical, since Biome formats `biome.json` itself)*

### Security & Configuration
- 🔧 **`modelShouldBeStrict()`** - Validates `Model::shouldBeStrict()` is called in AppServiceProvider with `true`, no argument, or a dynamic expression (not `false`) *(adds `Model::shouldBeStrict(! app()->isProduction())` to boot())*
- 🔧 **`formRequestFailOnUnknownFields()`** - Validates `FormRequest::failOnUnknownFields()` is called in AppServiceProvider (Laravel ≥13.6 only; warns on older versions) *(adds `FormRequest::failOnUnknownFields(! app()->isProduction())` to boot())*
- **`hasDailyLogging()`** - Validates logging uses `daily` channel (directly or via `stack`)
- **`hasEncryptedEnvFile()`** - Validates encrypted environment file exists
- **`usesReadableEncryptedEnvFile()`** - Validates the encrypted env file uses the readable line-per-variable format produced by `ddev artisan env:encrypt --readable` (variable names stay visible in diffs), not the opaque blob format. Passes when no encrypted file exists (existence is `hasEncryptedEnvFile`'s concern).
- **`doesNotPinOldMailTemplate()`** - Fails if a published mail view that pins the old template (`resources/views/vendor/mail/html/themes/default.css` or `html/header.blade.php`) exists, preventing adoption of Laravel's modernized mail template.
- 🔧 **`callsBaseline()`** - Validates self-validation runs after updates *(adds/upgrades post-update-cmd entry to include `--fix`)*
- **`doesNotCallPeriodicBaselineOnUpdate()`** - Fails if `php artisan limenet:laravel-baseline:periodic` is in the `post-update-cmd` scripts (it shouldn't be — periodic checks fail CI automatically when expired)
- 🔧 **`doesNotHaveGuidelinesScript()`** - Fails if the removed `php artisan limenet:laravel-baseline:guidelines` command is still in `post-update-cmd` (removed in v2.1.0) *(removes the entry from composer.json)*
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
