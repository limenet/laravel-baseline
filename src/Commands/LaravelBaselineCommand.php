<?php

namespace Limenet\LaravelBaseline\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Composer;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Rector\RectorVisitorClassFetch;
use Limenet\LaravelBaseline\Rector\RectorVisitorHasCall;
use Limenet\LaravelBaseline\Rector\RectorVisitorNamedArgument;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;

class LaravelBaselineCommand extends Command
{
    public $signature = 'limenet:laravel-baseline';

    public $description = 'Checks the project against a highly opinionated set of coding standards.';

    public function handle(): int
    {
        $errorCount = 0;
        $results = [];

        $this->info('Checking baseline...');
        $this->newLine(2);

        foreach ([
            $this->bumpsComposer(...),
            $this->hasCompleteRectorConfiguration(...),
            $this->hasEncryptedEnvFile(...),
            $this->isCiLintComplete(...),
            $this->isLaravelVersionMaintained(...),
            $this->doesNotUseIgnition(...),
            $this->usesIdeHelpers(...),
            $this->usesLarastan(...),
            $this->usesLaravelBoost(...),
            $this->usesLaravelHorizon(...),
            $this->usesLaravelPennant(...),
            $this->usesLaravelTelescope(...),
            $this->usesLimenetPintConfig(...),
            $this->usesPest(...),
            $this->usesPhpstanExtensions(...),
            $this->usesPredis(...),
            $this->usesRector(...),
            $this->usesSpatieBackup(...),
            $this->usesSpatieHealth(...),
        ] as $check) {
            $result = $check();
            $name = str((new \ReflectionFunction($check))->getName())->ucsplit()->implode(' ');

            $results[] = sprintf('%s %s', $result->icon(), $name);

            if ($result->isError()) {
                $errorCount++;
            }
        }

        $this->newLine(2);

        foreach ($results as $result) {
            $this->line($result);
        }

        return $errorCount === 0 ? Command::SUCCESS : Command::FAILURE;
    }

    private function getComposer(): Composer
    {
        return app(Composer::class)->setWorkingPath(base_path());
    }

    private function checkComposerPackages(string|array $packages): bool
    {
        $composer = $this->getComposer();
        $packages = is_string($packages) ? [$packages] : $packages;
        $this->comment('Composer check: '.implode(', ', $packages));
        foreach ($packages as $package) {
            if (! $composer->hasPackage($package)) {
                return false;
            }
        }

        return true;
    }

    private function checkComposerScript(string $scriptName, string $match): bool
    {
        $composer = base_path('composer.json');
        $composerJson = json_decode(file_get_contents($composer), true);

        $this->comment('Composer script check: '.$scriptName.' for '.$match);

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

    private function usesPest(): CheckResult
    {
        return $this->checkComposerPackages(['pestphp/pest', 'pestphp/pest-plugin-laravel'])
                && ! $this->checkComposerPackages(['pestphp/pest-plugin-drift', 'spatie/phpunit-watcher'])
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    private function usesIdeHelpers(): CheckResult
    {
        return $this->checkComposerPackages('barryvdh/laravel-ide-helper')
                && $this->hasPostUpdateScript('ide-helper:generate')
                && $this->hasPostUpdateScript('ide-helper:meta')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    private function bumpsComposer(): CheckResult
    {
        return $this->hasPostUpdateScript('composer bump')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    private function usesLaravelBoost(): CheckResult
    {
        return $this->checkComposerPackages('laravel/boost') ? CheckResult::PASS : CheckResult::FAIL;
    }

    private function usesLaravelHorizon(): CheckResult
    {
        if (! $this->checkComposerPackages('laravel/horizon')) {
            return CheckResult::WARN;
        }

        if (! $this->hasPostDeployScript('horizon:terminate')) {
            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    private function usesLaravelPennant(): CheckResult
    {
        if (! $this->checkComposerPackages('laravel/pennant')) {
            return CheckResult::WARN;
        }

        if (! $this->hasPostDeployScript('pennant:purge')) {
            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    private function doesNotUseIgnition(): CheckResult
    {
        return $this->checkComposerPackages('spatie/laravel-ignition') ? CheckResult::PASS : CheckResult::FAIL;
    }

    private function usesLaravelTelescope(): CheckResult
    {
        return $this->checkComposerPackages('laravel/telescope') ? CheckResult::PASS : CheckResult::FAIL;
    }

    private function usesLimenetPintConfig(): CheckResult
    {
        return $this->checkComposerPackages('limenet/laravel-pint-config') ? CheckResult::PASS : CheckResult::FAIL;
    }

    private function usesPredis(): CheckResult
    {
        return $this->checkComposerPackages('predis/predis') ? CheckResult::PASS : CheckResult::WARN;
    }

    private function usesSpatieHealth(): CheckResult
    {
        return $this->checkComposerPackages('spatie/laravel-health') ? CheckResult::PASS : CheckResult::WARN;
    }

    private function usesSpatieBackup(): CheckResult
    {
        return $this->checkComposerPackages('spatie/laravel-backup') ? CheckResult::PASS : CheckResult::WARN;
    }

    private function usesRector(): CheckResult
    {
        return $this->checkComposerPackages(['rector/rector', 'driftingly/rector-laravel']) ? CheckResult::PASS : CheckResult::WARN;
    }

    private function usesLarastan(): CheckResult
    {
        return $this->checkComposerPackages('larastan/larastan') ? CheckResult::PASS : CheckResult::FAIL;
    }

    private function usesPhpstanExtensions(): CheckResult
    {
        return $this->checkComposerPackages(['phpstan/phpstan-deprecation-rules', 'phpstan/phpstan-strict-rules']) ? CheckResult::PASS : CheckResult::FAIL;
    }

    private function isLaravelVersionMaintained(): CheckResult
    {
        return str(app()->version())->before('.')->toInteger() >= 11 ? CheckResult::PASS : CheckResult::FAIL;
    }

    private function hasEncryptedEnvFile(): CheckResult
    {
        return (new Finder)
            ->in(base_path())
            ->ignoreDotFiles(false)
            ->name('.env.*.encrypted')
            ->depth('== 0')
            ->hasResults()
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    private function isCiLintComplete(): CheckResult
    {
        return $this->checkComposerScript('ci-lint', 'pint')
                && $this->checkComposerScript('ci-lint', 'phpstan')
                && $this->checkComposerScript('ci-lint', 'rector')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    private function hasCompleteRectorConfiguration(): CheckResult
    {
        $rectorConfigFile = base_path('rector.php');

        if (! file_exists($rectorConfigFile)) {
            return CheckResult::FAIL;
        }

        $code = file_get_contents($rectorConfigFile);

        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        $ast = $parser->parse($code);

        $traverser = new NodeTraverser;

        $visitors = [
            new RectorVisitorNamedArgument($this, 'withComposerBased', ['phpunit', 'symfony', 'laravel']),
            new RectorVisitorNamedArgument($this, 'withPreparedSets', ['deadCode', 'codeQuality', 'codingStyle', 'typeDeclarations', 'privatization', 'instanceOf', 'earlyReturn', 'strictBooleans']),
            new RectorVisitorHasCall($this, 'withPhpSets'),
            new RectorVisitorClassFetch($this, 'withSetProviders', ['LaravelSetProvider']),
        ];

        foreach ($visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }

        $traverser->traverse($ast);

        foreach ($visitors as $visitor) {
            if (! $visitor->wasFound()) {
                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }
}
