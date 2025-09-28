<?php

namespace Limenet\LaravelBaseline\Commands;

use Illuminate\Console\Command;
use Limenet\LaravelBaseline\Checks\Checker;

class LaravelBaselineCommand extends Command
{
    public $signature = 'limenet:laravel-baseline';

    public $description = 'Checks the project against a highly opinionated set of coding standards.';

    public function handle(): int
    {
        $errorCount = 0;
        $results = [];
        $errors = [];

        $checker = new Checker($this);

        foreach ([
            $checker->bumpsComposer(...),
            $checker->callsBaseline(...),
            $checker->callsSentryHook(...),
            $checker->checkPhpunit(...),
            $checker->hasCompleteRectorConfiguration(...),
            $checker->hasEncryptedEnvFile(...),
            $checker->isCiLintComplete(...),
            $checker->isLaravelVersionMaintained(...),
            $checker->doesNotUseIgnition(...),
            $checker->hasCiJobs(...),
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
            $checker->usesSpatieBackup(...),
            $checker->usesSpatieHealth(...),
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
}
