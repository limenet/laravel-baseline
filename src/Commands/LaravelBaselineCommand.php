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
        $errors = [];

        foreach ([
            $this->bumpsComposer(...),
            $this->callsBaseline(...),
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
            $nameRaw = (new \ReflectionFunction($check))->getName();
            $name = str($nameRaw)->ucsplit()->implode(' ');

            if (in_array($nameRaw, config('baseline.excludes', []), true)) {
                $results[] = sprintf('⚪ %s (excluded)', $name);

                continue;
            }

            $result = $check();

            $line = sprintf('%s %s', $result->icon(), $name);
            $results[] = $line;

            if ($result->isError()) {
                $errors[] = $line;
                $errorCount++;
            }
        }

        if ($this->getOutput()->isVerbose()) {
            foreach ($results as $result) {
                $this->line($result);
            }
        }

        if ($this->getOutput()->isQuiet()) {
            foreach ($errors as $error) {
                $this->line($error);
            }
        }

        if ($errorCount !== 0) {
            $this->error("Baseline check failed with {$errorCount} error(s). Run with -v or -vv for more details.");

            return Command::FAILURE;
        }

        $this->info('Baseline check passed!');

        return Command::SUCCESS;
    }

    private function getComposer(): Composer
    {
        return app(Composer::class)->setWorkingPath(base_path());
    }

    private function checkComposerPackages(string|array $packages): bool
    {
        $composer = $this->getComposer();
        $packages = is_string($packages) ? [$packages] : $packages;

        if ($this->getOutput()->isVeryVerbose()) {
            $this->comment('Composer check: '.implode(', ', $packages));
        }

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

        if ($this->getOutput()->isVeryVerbose()) {
            $this->comment('Composer script check: '.$scriptName.' for '.$match);
        }

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
        return $this->checkComposerPackages('laravel/boost') ? CheckResult::PASS : CheckResult::WARN;
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
        return $this->checkComposerPackages('spatie/laravel-ignition') ? CheckResult::FAIL : CheckResult::PASS;
    }

    private function usesLaravelTelescope(): CheckResult
    {
        return $this->checkComposerPackages('laravel/telescope')
                && $this->hasPostUpdateScript('telescope:publish')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    private function usesLimenetPintConfig(): CheckResult
    {
        return $this->checkComposerPackages('limenet/laravel-pint-config')
                && $this->hasPostUpdateScript('laravel-pint-config:publish')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }

    private function callsBaseline(): CheckResult
    {
        return $this->hasPostUpdateScript('limenet:laravel-baseline')
            ? CheckResult::PASS
            : CheckResult::FAIL;
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
