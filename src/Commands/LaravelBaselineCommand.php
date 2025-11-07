<?php

namespace Limenet\LaravelBaseline\Commands;

use Illuminate\Console\Command;
use Limenet\LaravelBaseline\Checks\Checker;

class LaravelBaselineCommand extends Command
{
    public $signature = 'limenet:laravel-baseline:check';

    public $description = 'Checks the project against a highly opinionated set of coding standards.';

    public function handle(Checker $checker): int
    {
        $errorCount = 0;

        foreach ([
            $checker->bumpsComposer(...),
            $checker->callsBaseline(...),
            $checker->callsSentryHook(...),
            $checker->checkPhpunit(...),
            $checker->ddevHasPcovPackage(...),
            $checker->doesNotUseIgnition(...),
            $checker->doesNotUseSail(...),
            $checker->hasCompleteRectorConfiguration(...),
            $checker->hasEncryptedEnvFile(...),
            $checker->hasGuidelinesUpdateScript(...),
            $checker->hasNpmScripts(...),
            $checker->isCiLintComplete(...),
            $checker->isLaravelVersionMaintained(...),
            $checker->hasCiJobs(...),
            $checker->phpVersionMatchesCi(...),
            $checker->phpVersionMatchesDdev(...),
            $checker->usesIdeHelpers(...),
            $checker->usesLarastan(...),
            $checker->usesLaravelBoost(...),
            $checker->usesLaravelHorizon(...),
            $checker->usesLaravelPennant(...),
            $checker->usesLaravelPulse(...),
            $checker->usesLaravelTelescope(...),
            $checker->usesLimenetPintConfig(...),
            $checker->usesPest(...),
            $checker->usesPhpInsights(...),
            $checker->usesPhpstanExtensions(...),
            $checker->usesPredis(...),
            $checker->usesRector(...),
            $checker->usesReleaseIt(...),
            $checker->usesSpatieBackup(...),
            $checker->usesSpatieHealth(...),
        ] as $check) {
            $nameRaw = (new \ReflectionFunction($check))->getName();
            $name = str($nameRaw)->ucsplit()->implode(' ');

            if (in_array($nameRaw, config('baseline.excludes', []), true)) {
                $this->line(sprintf('⚪ %s (excluded)', $name));

                continue;
            }

            $checker->resetComments();
            $result = $check();
            $comments = $checker->getComments();

            $errorCount += $result->isError() ? 1 : 0;

            if ($result->isError() || $this->getOutput()->isVerbose()) {
                $this->line(sprintf('%s %s', $result->icon(), $name));
            }

            if ($result->isError() || $this->getOutput()->isVeryVerbose()) {
                foreach ($comments as $comment) {
                    $this->comment($comment);
                }
            }

        }

        if ($errorCount !== 0) {
            $this->error("Baseline check failed with {$errorCount} error(s). Run with -v or -vv for more details.");

            return Command::FAILURE;
        }

        $this->info('Baseline check passed!');

        return Command::SUCCESS;
    }
}
