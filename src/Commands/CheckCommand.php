<?php

namespace Limenet\LaravelBaseline\Commands;

use Illuminate\Console\Command;
use Limenet\LaravelBaseline\Project\LaravelProject;
use Limenet\LaravelBaseline\Runner\CheckRunner;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

class CheckCommand extends Command
{
    public $signature = 'limenet:laravel-baseline:check {--fix : Automatically fix issues where possible}';

    public $description = 'Checks the project against a highly opinionated set of coding standards.';

    public function handle(): int
    {
        intro('Running baseline checks...');

        $errorCount = (new CheckRunner(new LaravelProject, $this->getOutput()))
            ->run((bool) $this->option('fix'));

        if ($errorCount !== 0) {
            error(
                "Baseline check failed with {$errorCount} error(s). Run with -v or -vv for more details.",
            );

            return Command::FAILURE;
        }

        outro('Baseline check passed!');

        return Command::SUCCESS;
    }
}
