<?php

namespace Limenet\LaravelBaseline\Checks;

use Illuminate\Support\Composer;
use Illuminate\Support\Facades\Schedule;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Rector\RectorVisitorArrayArgument;
use Limenet\LaravelBaseline\Rector\RectorVisitorClassFetch;
use Limenet\LaravelBaseline\Rector\RectorVisitorHasCall;
use Limenet\LaravelBaseline\Rector\RectorVisitorNamedArgument;
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
        return $this->checkComposerPackages('laravel/boost') ? CheckResult::PASS : CheckResult::WARN;
    }

    public function usesLaravelHorizon(): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/horizon')) {
            return CheckResult::WARN;
        }

        if (!$this->hasPostDeployScript('horizon:terminate')) {
            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function usesLaravelPennant(): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/pennant')) {
            return CheckResult::WARN;
        }

        if (!$this->hasPostDeployScript('pennant:purge')) {
            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function usesLaravelPulse(): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/pulse')) {
            return CheckResult::WARN;
        }

        if (!$this->hasScheduleEntry('pulse:trim')) {
            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function doesNotUseIgnition(): CheckResult
    {
        return $this->checkComposerPackages('spatie/laravel-ignition') ? CheckResult::FAIL : CheckResult::PASS;
    }

    public function usesLaravelTelescope(): CheckResult
    {
        return $this->checkComposerPackages('laravel/telescope')
        && $this->hasPostUpdateScript('telescope:publish')
        && $this->hasScheduleEntry('telescope:prune')
            ? CheckResult::PASS
            : CheckResult::FAIL;
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
        return $this->hasPostUpdateScript('limenet:laravel-baseline')
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
            if (($data[$jobName] ?? null) !== ['extends' => [$extends]]) {
                $this->addComment("Could not find job $jobName extending $extends in .gitlab-ci.yml");

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
            $this->addComment('Could not find correctly configured Sentry release hook in .gitlab-ci.yml');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    public function usesPredis(): CheckResult
    {
        return $this->checkComposerPackages('predis/predis') ? CheckResult::PASS : CheckResult::WARN;
    }

    public function usesSpatieHealth(): CheckResult
    {
        if (!$this->checkComposerPackages('spatie/laravel-health')) {
            return CheckResult::WARN;
        }

        return $this->hasScheduleEntry('health:check')
        && $this->hasScheduleEntry('health:schedule-check-heartbeat')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function usesSpatieBackup(): CheckResult
    {
        if (!$this->checkComposerPackages('spatie/laravel-backup')) {
            return CheckResult::WARN;
        }

        return $this->hasScheduleEntry('backup:run')
        && $this->hasScheduleEntry('backup:clean')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function usesRector(): CheckResult
    {
        return $this->checkComposerPackages(['rector/rector', 'driftingly/rector-laravel']) ? CheckResult::PASS : CheckResult::WARN;
    }

    public function usesLarastan(): CheckResult
    {
        return $this->checkComposerPackages('larastan/larastan') ? CheckResult::PASS : CheckResult::FAIL;
    }

    public function usesPhpstanExtensions(): CheckResult
    {
        return $this->checkComposerPackages(['phpstan/phpstan-deprecation-rules', 'phpstan/phpstan-strict-rules']) ? CheckResult::PASS : CheckResult::FAIL;
    }

    public function usesPhpInsights(): CheckResult
    {
        return $this->checkComposerPackages('nunomaduro/phpinsights') ? CheckResult::PASS : CheckResult::FAIL;
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
        && $this->checkComposerScript('ci-lint', 'rector')
        && $this->checkComposerScript('ci-lint', 'insights --summary --no-interaction')
        && $this->checkComposerScript('ci-lint', 'insights -n --ansi --format=codeclimate > codeclimate-report.json 2>/dev/null')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    public function checkPhpunit(): CheckResult
    {
        $xmlFile = base_path('/phpunit.xml');

        if (!file_exists($xmlFile)) {
            $this->addComment('PHPUnit XML file not found');

            return CheckResult::FAIL;
        }

        $xml = simplexml_load_string(file_get_contents($xmlFile) ?: '');

        if ($xml === false) {
            $this->addComment('Could not parse PHPUnit XML file');

            return CheckResult::FAIL;
        }

        if (
            ($xml->coverage->report->cobertura ?? null) === null
            || (string) $xml->coverage->report->cobertura->attributes()['outputFile'] !== 'cobertura.xml'
        ) {
            $this->addComment('Cobertura missing / incorrectly configured');

            return CheckResult::FAIL;
        }
        if (
            ($xml->logging->junit ?? null) === null
            || (string) $xml->logging->junit->attributes()['outputFile'] !== 'report.xml'
        ) {
            $this->addComment('JUnit missing / incorrectly configured');

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
            $this->addComment('APP_KEY not defined in <php><server>');

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
            new RectorVisitorNamedArgument($this, 'withPreparedSets', ['deadCode', 'codeQuality', 'codingStyle', 'typeDeclarations', 'privatization', 'instanceOf', 'earlyReturn', 'strictBooleans']),
            new RectorVisitorNamedArgument($this, 'withImportNames', ['!importShortClasses']),
            new RectorVisitorHasCall($this, 'withPhpSets'),
            new RectorVisitorClassFetch($this, 'withSetProviders', ['LaravelSetProvider']),
            new RectorVisitorArrayArgument($this, 'withRules', ['AddGenericReturnTypeToRelationsRector']),
        ];

        foreach ($visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }

        $traverser->traverse($ast);

        foreach ($visitors as $visitor) {
            if (!$visitor->wasFound()) {
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
        $composer = base_path('composer.json');
        $composerJson = json_decode(
            file_get_contents($composer) ?: throw new \RuntimeException(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

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
            $this->addComment('Gitlab CI file not found');

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
}
