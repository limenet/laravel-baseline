<?php

namespace Limenet\LaravelBaseline\Checks;

use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Schedule;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Rector\RectorVisitorArrayArgument;
use Limenet\LaravelBaseline\Rector\RectorVisitorClassFetch;
use Limenet\LaravelBaseline\Rector\RectorVisitorHasCall;
use Limenet\LaravelBaseline\Rector\RectorVisitorNamedArgument;
use Limenet\LaravelBaseline\Rector\RectorVisitorPaths;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Yaml\Yaml;

class Checker
{
    /**
     * @var string[]
     */
    private array $comments;

    public function usesPest(): CheckResult
    {
        return $this->checkComposerPackages(['pestphp/pest', 'pestphp/pest-plugin-laravel'])
        && !$this->checkComposerPackages(['pestphp/pest-plugin-drift', 'spatie/phpunit-watcher'])
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function usesIdeHelpers(): CheckResult
    {
        return $this->checkComposerPackages('barryvdh/laravel-ide-helper')
        && $this->hasPostUpdateScript('ide-helper:generate')
        && $this->hasPostUpdateScript('ide-helper:meta')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function bumpsComposer(): CheckResult
    {
        return $this->hasPostUpdateScript('composer bump')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function usesLaravelBoost(): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/boost')) {
            return CheckResult::FAIL;
        }

        if (!$this->hasPostUpdateScript('boost:update')) {
            return CheckResult::FAIL;
        }

        // Check boost.json file exists and has correct configuration
        $boostJsonFile = base_path('boost.json');

        if (!file_exists($boostJsonFile)) {
            $this->addComment('Laravel Boost configuration missing: Create boost.json in project root');

            return CheckResult::FAIL;
        }

        $boostConfig = json_decode(
            file_get_contents($boostJsonFile) ?: throw new \RuntimeException(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $requiredAgents = ['claude_code', 'phpstorm'];
        $requiredEditors = ['claude_code', 'phpstorm', 'vscode'];

        $actualAgents = $boostConfig['agents'] ?? [];
        $actualEditors = $boostConfig['editors'] ?? [];

        // Check if all required agents are present
        $missingAgents = array_diff($requiredAgents, $actualAgents);
        if (!empty($missingAgents)) {
            $this->addComment('Laravel Boost configuration incomplete: boost.json must include agents: '.implode(', ', $requiredAgents));

            return CheckResult::FAIL;
        }

        // Check if all required editors are present
        $missingEditors = array_diff($requiredEditors, $actualEditors);
        if (!empty($missingEditors)) {
            $this->addComment('Laravel Boost configuration incomplete: boost.json must include editors: '.implode(', ', $requiredEditors));

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function usesLaravelHorizon(): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/horizon')) {
            return CheckResult::FAIL;
        }

        return $this->hasPostDeployScript('horizon:terminate')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function usesLaravelPennant(): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/pennant')) {
            return CheckResult::WARN;
        }

        return $this->hasPostDeployScript('pennant:purge')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function usesLaravelPulse(): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/pulse')) {
            return CheckResult::FAIL;
        }

        if (!$this->hasScheduleEntry('pulse:trim')) {
            return CheckResult::FAIL;
        }

        if (!$this->checkPhpunitEnvVar('PULSE_ENABLED', 'false')) {
            $this->addComment('Missing or incorrect environment variable in phpunit.xml: Add <env name="PULSE_ENABLED" value="false"/> to <php> section');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function doesNotUseIgnition(): CheckResult
    {
        return $this->checkComposerPackages('spatie/laravel-ignition')
            ? CheckResult::FAIL
            : CheckResult::PASS;
    }

    public function doesNotUseSail(): CheckResult
    {
        if ($this->checkComposerPackages('laravel/sail')) {
            return CheckResult::FAIL;
        }

        if (file_exists(base_path('docker-compose.yml'))) {
            $this->addComment('docker-compose.yml file should be removed from project root');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function usesLaravelTelescope(): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/telescope')) {
            return CheckResult::FAIL;
        }

        if (!$this->hasPostUpdateScript('telescope:publish')) {
            return CheckResult::FAIL;
        }

        if (!$this->hasScheduleEntry('telescope:prune')) {
            return CheckResult::FAIL;
        }

        if (!$this->checkPhpunitEnvVar('TELESCOPE_ENABLED', 'false')) {
            $this->addComment('Missing or incorrect environment variable in phpunit.xml: Add <env name="TELESCOPE_ENABLED" value="false"/> to <php> section');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function usesLimenetPintConfig(): CheckResult
    {
        return $this->checkComposerPackages('limenet/laravel-pint-config')
        && $this->hasPostUpdateScript('laravel-pint-config:publish')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function callsBaseline(): CheckResult
    {
        return $this->hasPostUpdateScript('limenet:laravel-baseline:check')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function hasCiJobs(): CheckResult
    {
        $data = $this->getGitlabCiData();
        $jobs = [
            'build' => '.build',
            'php' => '.lint_php',
            'js' => '.lint_js',
            'test' => '.test',
        ];
        foreach ($jobs as $jobName => $extends) {
            if (!isset($data[$jobName]['extends']) || $data[$jobName]['extends'] !== [$extends]) {
                $this->addComment("Missing or misconfigured CI job in .gitlab-ci.yml: Add job '$jobName' with 'extends: [$extends]'");

                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }

    public function callsSentryHook(): CheckResult
    {
        if (!$this->checkComposerPackages('sentry/sentry-laravel')) {
            return CheckResult::WARN;
        }

        $data = $this->getGitlabCiData();

        if (
            ($data['release']['extends'][0] ?? null) !== '.release'
            || !str_starts_with($data['release']['variables']['SENTRY_RELEASE_WEBHOOK'] ?? '', 'https://sentry.io/api/hooks/release/builtin/')
        ) {
            $this->addComment('Sentry release hook missing or misconfigured in .gitlab-ci.yml: Job "release" must extend ".release" and set SENTRY_RELEASE_WEBHOOK variable to a valid Sentry webhook URL');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function usesPredis(): CheckResult
    {
        return $this->checkComposerPackages('predis/predis')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function usesSpatieHealth(): CheckResult
    {
        return $this->checkPackageWithSchedule(
            'spatie/laravel-health',
            ['health:check', 'health:schedule-check-heartbeat'],
        );
    }

    public function usesSpatieBackup(): CheckResult
    {
        return $this->checkPackageWithSchedule(
            'spatie/laravel-backup',
            ['backup:run', 'backup:clean'],
        );
    }

    public function usesRector(): CheckResult
    {
        return $this->checkComposerPackages([
            'rector/rector',
            'driftingly/rector-laravel',
        ])
        && $this->checkComposerScript('ci-lint', 'rector')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function usesLarastan(): CheckResult
    {
        return $this->checkComposerPackages('larastan/larastan')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function usesPhpstanExtensions(): CheckResult
    {
        return $this->checkComposerPackages([
            'phpstan/extension-installer',
            'phpstan/phpstan-deprecation-rules',
            'phpstan/phpstan-strict-rules',
        ])
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function phpstanLevelAtLeastEight(): CheckResult
    {
        $phpstanConfigFile = base_path('phpstan.neon');

        if (!file_exists($phpstanConfigFile)) {
            $this->addComment('PHPStan configuration missing: Create phpstan.neon in project root');

            return CheckResult::FAIL;
        }

        $phpstanConfig = Yaml::parseFile($phpstanConfigFile);

        $level = $phpstanConfig['parameters']['level'] ?? null;

        if ($level === null) {
            $this->addComment('PHPStan level not configured: Add "level" parameter to phpstan.neon');

            return CheckResult::FAIL;
        }

        // Handle both numeric and string levels (e.g., 8 or "8" or "max")
        if ($level === 'max') {
            return CheckResult::PASS;
        }

        $levelInt = is_numeric($level) ? (int) $level : null;

        if ($levelInt === null) {
            $this->addComment('PHPStan level must be a number or "max": Found "'.$level.'" in phpstan.neon');

            return CheckResult::FAIL;
        }

        if ($levelInt < 8) {
            $this->addComment('PHPStan level must be at least 8: Found level '.$levelInt.' in phpstan.neon (set to 8 or higher)');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function usesPhpInsights(): CheckResult
    {
        return $this->checkComposerPackages('nunomaduro/phpinsights')
        && $this->checkComposerScript('ci-lint', 'insights --summary --no-interaction')
        && $this->checkComposerScript('ci-lint', 'insights -n --ansi --format=codeclimate > codeclimate-report.json 2>/dev/null')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function isLaravelVersionMaintained(): CheckResult
    {
        return str(app()->version())->before('.')->toInteger() >= 11 ? CheckResult::PASS : CheckResult::FAIL;
    }

    public function hasEncryptedEnvFile(): CheckResult
    {
        return (new Finder())
            ->in(base_path())
            ->ignoreDotFiles(false)
            ->name('.env.*.encrypted')
            ->depth('== 0')
            ->hasResults()
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function isCiLintComplete(): CheckResult
    {
        return $this->checkComposerScript('ci-lint', 'pint --parallel')
        && $this->checkComposerScript('ci-lint', 'phpstan')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function checkPhpunit(): CheckResult
    {
        $xml = $this->getPhpunitXml();

        if ($xml === null) {
            $this->addComment('PHPUnit configuration missing: Create phpunit.xml in project root');

            return CheckResult::FAIL;
        }

        if ($xml === false) {
            $this->addComment('PHPUnit configuration invalid: Check phpunit.xml for XML syntax errors');

            return CheckResult::FAIL;
        }

        if (
            ($xml->coverage->report->cobertura ?? null) === null
            || (string) $xml->coverage->report->cobertura->attributes()['outputFile'] !== 'cobertura.xml'
        ) {
            $this->addComment('Cobertura coverage report missing or misconfigured in phpunit.xml: Add <cobertura outputFile="cobertura.xml"/> under <coverage><report>');

            return CheckResult::FAIL;
        }
        if (
            ($xml->logging->junit ?? null) === null
            || (string) $xml->logging->junit->attributes()['outputFile'] !== 'report.xml'
        ) {
            $this->addComment('JUnit report missing or misconfigured in phpunit.xml: Add <junit outputFile="report.xml"/> under <logging>');

            return CheckResult::FAIL;
        }

        $appKeyFound = false;

        foreach ($xml->php->env as $env) {
            $attrs = $env->attributes();
            if ((string) $attrs['name'] === 'APP_KEY' && str_starts_with($attrs['value'], 'base64:')) {
                $appKeyFound = true;
                break;
            }
        }

        if (!$appKeyFound) {
            $this->addComment('APP_KEY missing in phpunit.xml: Add <env name="APP_KEY" value="base64:..."/> to <php> section (generate with "php artisan key:generate")');

            return CheckResult::FAIL;
        }

        // Check for source configuration
        if (
            ($xml->source->include->directory ?? null) === null
            || (string) $xml->source->include->directory !== './app'
            || (string) $xml->source->include->directory->attributes()['suffix'] !== '.php'
        ) {
            $this->addComment('Coverage source configuration missing or incorrect in phpunit.xml: Add <source><include><directory suffix=".php">./app</directory></include></source>');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;

    }

    public function hasCompleteRectorConfiguration(): CheckResult
    {
        $rectorConfigFile = base_path('rector.php');

        if (!file_exists($rectorConfigFile)) {
            return CheckResult::FAIL;
        }

        $code = file_get_contents($rectorConfigFile) ?: throw new \RuntimeException();

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code) ?: throw new \RuntimeException();

        $traverser = new NodeTraverser();

        $visitors = [
            new RectorVisitorNamedArgument($this, 'withComposerBased', ['phpunit', 'symfony', 'laravel']),
            new RectorVisitorNamedArgument($this, 'withPreparedSets', ['deadCode', 'codeQuality', 'codingStyle', 'typeDeclarations', 'privatization', 'instanceOf', 'earlyReturn']),
            new RectorVisitorNamedArgument($this, 'withImportNames', ['!importShortClasses']),
            new RectorVisitorHasCall($this, 'withPhpSets'),
            new RectorVisitorHasCall($this, 'withAttributesSets'),
            new RectorVisitorClassFetch($this, 'withSetProviders', ['LaravelSetProvider']),
            new RectorVisitorArrayArgument($this, 'withRules', ['AddGenericReturnTypeToRelationsRector']),
            // new RectorVisitorArrayArgument($this, 'withSkip', ['FunctionLikeToFirstClassCallableRector']),
            new RectorVisitorPaths($this, 'withPaths', ['app', 'database', 'routes', 'tests']),
        ];

        foreach ($visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }

        $traverser->traverse($ast);

        foreach ($visitors as $visitor) {
            if (!$visitor->wasFound()) {
                $this->addComment($visitor->getErrorMessage());

                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }

    public function phpVersionMatchesCi(): CheckResult
    {
        $composerPhpVersion = $this->getComposerPhpVersion();

        if ($composerPhpVersion === null) {
            return CheckResult::FAIL;
        }

        try {
            $ciData = $this->getGitlabCiData();
        } catch (\RuntimeException) {
            return CheckResult::FAIL;
        }

        $ciPhpVersion = $ciData['variables']['PHP_VERSION'] ?? null;

        if ($ciPhpVersion === null) {
            $this->addComment('Missing PHP_VERSION variable in .gitlab-ci.yml: Add "PHP_VERSION" to the variables section');

            return CheckResult::FAIL;
        }

        // Ensure CI PHP version matches the composer constraint (both should be in format X.Y)
        if ($composerPhpVersion !== $ciPhpVersion) {
            $this->addComment(sprintf(
                'PHP version mismatch: composer.json requires ^%s but .gitlab-ci.yml uses %s',
                $composerPhpVersion,
                $ciPhpVersion,
            ));

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function phpVersionMatchesDdev(): CheckResult
    {
        $composerPhpVersion = $this->getComposerPhpVersion();

        if ($composerPhpVersion === null) {
            return CheckResult::FAIL;
        }

        $ddevConfig = $this->getDdevConfig();

        if ($ddevConfig === null) {
            return CheckResult::FAIL;
        }

        $ddevPhpVersion = $ddevConfig['php_version'] ?? null;

        if ($ddevPhpVersion === null) {
            $this->addComment('DDEV configuration missing php_version: Add "php_version" to .ddev/config.yaml');

            return CheckResult::FAIL;
        }

        // Ensure DDEV PHP version matches the composer constraint (both should be in format X.Y)
        if ($composerPhpVersion !== $ddevPhpVersion) {
            $this->addComment(sprintf(
                'PHP version mismatch: composer.json requires ^%s but .ddev/config.yaml uses %s',
                $composerPhpVersion,
                $ddevPhpVersion,
            ));

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function ddevHasPcovPackage(): CheckResult
    {
        $ddevConfig = $this->getDdevConfig();

        if ($ddevConfig === null) {
            return CheckResult::FAIL;
        }

        $extraPackages = $ddevConfig['webimage_extra_packages'] ?? null;

        if ($extraPackages === null) {
            $this->addComment('DDEV missing pcov package configuration: Add "webimage_extra_packages" to .ddev/config.yaml');

            return CheckResult::FAIL;
        }

        if (!is_array($extraPackages)) {
            $this->addComment('DDEV configuration error: "webimage_extra_packages" must be an array in .ddev/config.yaml');

            return CheckResult::FAIL;
        }

        // Check if pcov package is in the list (with the DDEV_PHP_VERSION variable)
        $pcovPackage = 'php${DDEV_PHP_VERSION}-pcov';

        if (!in_array($pcovPackage, $extraPackages, true)) {
            $this->addComment(sprintf(
                'DDEV missing pcov package: Add "%s" to webimage_extra_packages in .ddev/config.yaml',
                $pcovPackage,
            ));

            return CheckResult::FAIL;
        }

        // Check .ddev/php/90-custom.ini exists and has correct content
        $customIniFile = base_path('.ddev/php/90-custom.ini');

        if (!file_exists($customIniFile)) {
            $this->addComment('DDEV PHP configuration missing: Create .ddev/php/90-custom.ini with [PHP] section and opcache.jit=disable');

            return CheckResult::FAIL;
        }

        $iniContent = file_get_contents($customIniFile) ?: '';

        if (!str_starts_with(trim($iniContent), '[PHP]')) {
            $this->addComment('DDEV PHP configuration invalid: .ddev/php/90-custom.ini must start with [PHP] section');

            return CheckResult::FAIL;
        }

        if (!str_contains($iniContent, 'opcache.jit=disable')) {
            $this->addComment('DDEV PHP configuration incomplete: Add "opcache.jit=disable" to .ddev/php/90-custom.ini');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function ddevMutagenIgnoresNodeModules(): CheckResult
    {
        $mutagenConfigFile = base_path('.ddev/mutagen/mutagen.yml');

        if (!file_exists($mutagenConfigFile)) {
            $this->addComment('DDEV Mutagen configuration missing: Create .ddev/mutagen/mutagen.yml');

            return CheckResult::FAIL;
        }

        // Check if mutagen config is not ignored in .ddev/.gitignore
        $ddevGitignoreFile = base_path('.ddev/.gitignore');

        if (file_exists($ddevGitignoreFile)) {
            $gitignoreContent = file_get_contents($ddevGitignoreFile) ?: '';

            // Check if .gitignore is auto-generated
            if (str_contains($gitignoreContent, '#ddev-generated')) {
                $this->addComment('DDEV .gitignore is auto-generated: Remove "#ddev-generated" comment from .ddev/.gitignore to prevent DDEV from regenerating it');

                return CheckResult::FAIL;
            }

            $gitignoreLines = array_map('trim', explode("\n", $gitignoreContent));

            // Check for patterns that would ignore the gitignore file itself
            $ignoringSelf = false;
            foreach ($gitignoreLines as $line) {
                // Skip comments and empty lines
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                // Check if line matches patterns that would ignore .gitignore itself
                if ($line === '/.gitignore' || $line === '.gitignore') {
                    $ignoringSelf = true;
                    break;
                }
            }

            if ($ignoringSelf) {
                $this->addComment('DDEV .gitignore is ignoring itself: Remove "/.gitignore" from .ddev/.gitignore to track the gitignore file');

                return CheckResult::FAIL;
            }

            // Check for patterns that would ignore mutagen.yml
            $ignoringMutagen = false;
            foreach ($gitignoreLines as $line) {
                // Skip comments and empty lines
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                // Check if line matches patterns that would ignore mutagen.yml
                if ($line === '/mutagen/mutagen.yml' || $line === 'mutagen/mutagen.yml' || $line === '/mutagen/' || $line === 'mutagen/') {
                    $ignoringMutagen = true;
                    break;
                }
            }

            if ($ignoringMutagen) {
                $this->addComment('DDEV Mutagen configuration is ignored by git: Remove "/mutagen/mutagen.yml" from .ddev/.gitignore to track the configuration');

                return CheckResult::FAIL;
            }
        }

        // Check if the file contains #ddev-generated comment
        $mutagenConfigContent = file_get_contents($mutagenConfigFile) ?: '';

        if (str_contains($mutagenConfigContent, '#ddev-generated')) {
            $this->addComment('DDEV Mutagen configuration is auto-generated: Remove "#ddev-generated" comment from .ddev/mutagen/mutagen.yml to prevent DDEV from overwriting your changes');

            return CheckResult::FAIL;
        }

        $mutagenConfig = Yaml::parseFile($mutagenConfigFile);

        // Check if sync.defaults.ignore.paths exists and contains "/node_modules"
        $ignorePaths = $mutagenConfig['sync']['defaults']['ignore']['paths'] ?? [];

        if (!in_array('/node_modules', $ignorePaths, true)) {
            $this->addComment('DDEV Mutagen configuration incomplete: Add "/node_modules" to sync.defaults.ignore.paths in .ddev/mutagen/mutagen.yml and run "ddev mutagen reset" to apply changes');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function usesReleaseIt(): CheckResult
    {
        // Check if release-it and @release-it/bumper are in devDependencies
        if (!$this->checkNpmPackages(['release-it', '@release-it/bumper'])) {
            return CheckResult::FAIL;
        }

        // Check if release npm script exists
        if (!$this->checkNpmScript('release', 'release-it')) {
            $this->addComment('Missing release script in package.json: Add "release": "release-it" to scripts section');

            return CheckResult::FAIL;
        }

        // Check if .release-it.json exists and has correct configuration
        $releaseItConfig = $this->getReleaseItConfig();

        if ($releaseItConfig === null) {
            return CheckResult::FAIL;
        }

        // Check for plugins configuration
        $bumperPlugin = $releaseItConfig['plugins']['@release-it/bumper'] ?? null;

        if ($bumperPlugin === null) {
            $this->addComment('Missing @release-it/bumper plugin configuration in .release-it.json: Add plugins section with @release-it/bumper');

            return CheckResult::FAIL;
        }

        // Check bumper plugin out configuration
        $outFile = $bumperPlugin['out']['file'] ?? null;
        $outPath = $bumperPlugin['out']['path'] ?? null;

        if ($outFile !== 'composer.json' || $outPath !== 'version') {
            $this->addComment('Invalid @release-it/bumper configuration in .release-it.json: Set out.file to "composer.json" and out.path to "version"');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function hasNpmScripts(): CheckResult
    {
        $packageJson = $this->getPackageJson();

        if ($packageJson === null) {
            return CheckResult::FAIL;
        }

        // Check if ci-lint npm script exists
        if (!isset($packageJson['scripts']['ci-lint'])) {
            $this->addComment('Missing ci-lint script in package.json: Add "ci-lint" to scripts section');

            return CheckResult::FAIL;
        }

        // Check if production npm script exists
        if (!isset($packageJson['scripts']['production'])) {
            $this->addComment('Missing production script in package.json: Add "production" to scripts section');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function hasGuidelinesUpdateScript(): CheckResult
    {
        if (!$this->hasPostUpdateScript('limenet:laravel-baseline:guidelines')) {
            $this->addComment('Missing guidelines update script in composer.json: Add "@php artisan limenet:laravel-baseline:guidelines" to post-update-cmd section');

            return CheckResult::FAIL;
        }

        // Check that guidelines command comes before boost:update
        $composerJson = $this->getComposerJson();
        if ($composerJson !== null) {
            $postUpdateScripts = $composerJson['scripts']['post-update-cmd'] ?? [];
            $guidelinesIndex = null;
            $boostIndex = null;

            foreach ($postUpdateScripts as $index => $script) {
                if (str_contains($script, 'limenet:laravel-baseline:guidelines')) {
                    $guidelinesIndex = $index;
                }
                if (str_contains($script, 'boost:update')) {
                    $boostIndex = $index;
                }
            }

            // If both exist, guidelines must come before boost
            if ($guidelinesIndex !== null && $boostIndex !== null && $guidelinesIndex > $boostIndex) {
                $this->addComment('Guidelines update script must be called before boost:update in composer.json post-update-cmd section');

                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }

    /** @return string[] */
    public function getComments(): array
    {
        return $this->comments;
    }

    public function resetComments(): void
    {
        $this->comments = [];
    }

    public function addComment(string $comment): void
    {
        $this->comments[] = $comment;
    }

    private function getComposer(): Composer
    {
        return app(Composer::class)->setWorkingPath(base_path());
    }

    /**
     * @param  string|list<string>  $packages
     */
    private function checkComposerPackages(string|array $packages): bool
    {
        $composer = $this->getComposer();
        $packages = is_string($packages) ? [$packages] : $packages;

        $this->addComment('Composer check: '.implode(', ', $packages));

        foreach ($packages as $package) {
            if (!$composer->hasPackage($package)) {
                return false;
            }
        }

        return true;
    }

    private function checkComposerScript(string $scriptName, string $match): bool
    {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return false;
        }

        $this->addComment('Composer script check: '.$scriptName.' for '.$match);

        foreach ($composerJson['scripts'][$scriptName] ?? [] as $script) {
            if (str($script)->contains($match)) {
                return true;
            }
        }

        return false;
    }

    private function hasPostDeployScript(string $match): bool
    {
        return $this->checkComposerScript('ci-deploy-post', $match);
    }

    private function hasPostUpdateScript(string $match): bool
    {
        return $this->checkComposerScript('post-update-cmd', $match);
    }

    /**
     * @return array<string,mixed>
     */
    private function getGitlabCiData(): array
    {
        $ciFile = base_path('/.gitlab-ci.yml');

        if (!file_exists($ciFile)) {
            $this->addComment('GitLab CI configuration missing: Create .gitlab-ci.yml in project root');

            throw new \RuntimeException();
        }

        return Yaml::parseFile($ciFile);
    }

    private function hasScheduleEntry(string $command): bool
    {
        $this->addComment('Schedule check: '.$command);

        foreach (Schedule::events() as $event) {
            if (str_contains($event->command ?? '', $command)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Checks if a package is installed and has required schedule entries
     *
     * @param  string|list<string>  $scheduleCommands
     */
    private function checkPackageWithSchedule(
        string $package,
        string|array $scheduleCommands,
    ): CheckResult {
        if (!$this->checkComposerPackages($package)) {
            return CheckResult::WARN;
        }

        $commands = is_string($scheduleCommands) ? [$scheduleCommands] : $scheduleCommands;

        foreach ($commands as $command) {
            if (!$this->hasScheduleEntry($command)) {
                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }

    private function getPhpunitXml(): \SimpleXMLElement|false|null
    {
        $xmlFile = base_path('/phpunit.xml');

        if (!file_exists($xmlFile)) {
            return null;
        }

        return simplexml_load_string(file_get_contents($xmlFile) ?: '');
    }

    private function checkPhpunitEnvVar(string $name, string $expectedValue): bool
    {
        $xml = $this->getPhpunitXml();

        if ($xml === null || $xml === false) {
            return false;
        }

        foreach ($xml->php->env as $env) {
            $attrs = $env->attributes();
            if ((string) $attrs['name'] === $name && (string) $attrs['value'] === $expectedValue) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getComposerJson(): ?array
    {
        $composerFile = base_path('composer.json');

        if (!file_exists($composerFile)) {
            $this->addComment('Composer configuration missing: composer.json not found in project root');

            return null;
        }

        return json_decode(
            file_get_contents($composerFile) ?: throw new \RuntimeException(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getDdevConfig(): ?array
    {
        $ddevConfigFile = base_path('.ddev/config.yaml');

        if (!file_exists($ddevConfigFile)) {
            $this->addComment('DDEV configuration missing: .ddev/config.yaml not found');

            return null;
        }

        return Yaml::parseFile($ddevConfigFile);
    }

    private function getComposerPhpVersion(): ?string
    {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return null;
        }

        $phpConstraint = $composerJson['require']['php'] ?? null;

        if ($phpConstraint === null) {
            $this->addComment('PHP version not defined: Add "php" requirement to composer.json');

            return null;
        }

        // Extract the minimum version from the constraint (e.g., "^8.2" -> "8.2")
        if (!preg_match('/\^?(\d+\.\d+)/', $phpConstraint, $matches)) {
            $this->addComment('PHP version format invalid in composer.json: Use format "^X.Y" (e.g., "^8.4"), found: '.$phpConstraint);

            return null;
        }

        return $matches[1];
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getPackageJson(): ?array
    {
        $packageFile = base_path('package.json');

        if (!file_exists($packageFile)) {
            $this->addComment('Package.json missing: Create package.json in project root');

            return null;
        }

        return json_decode(
            file_get_contents($packageFile) ?: throw new \RuntimeException(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @return array<string,mixed>|null
     */
    private function getReleaseItConfig(): ?array
    {
        $releaseItFile = base_path('.release-it.json');

        if (!file_exists($releaseItFile)) {
            $this->addComment('Release-it configuration missing: Create .release-it.json in project root');

            return null;
        }

        return json_decode(
            file_get_contents($releaseItFile) ?: throw new \RuntimeException(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );
    }

    /**
     * @param  string|list<string>  $packages
     */
    private function checkNpmPackages(string|array $packages, string $packageType = 'devDependencies'): bool
    {
        $packageJson = $this->getPackageJson();

        if ($packageJson === null) {
            return false;
        }

        $packages = is_string($packages) ? [$packages] : $packages;

        $this->addComment('NPM check ('.$packageType.'): '.implode(', ', $packages));

        foreach ($packages as $package) {
            if (!isset($packageJson[$packageType][$package])) {
                return false;
            }
        }

        return true;
    }

    private function checkNpmScript(string $scriptName, string $match): bool
    {
        $packageJson = $this->getPackageJson();

        if ($packageJson === null) {
            return false;
        }

        $this->addComment('NPM script check: '.$scriptName.' for '.$match);

        $script = $packageJson['scripts'][$scriptName] ?? null;

        if ($script === null) {
            return false;
        }

        return str_contains($script, $match);
    }
}
