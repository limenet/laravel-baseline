<?php

namespace Limenet\LaravelBaseline\Cli\Command;

use Limenet\LaravelBaseline\Runner\CheckRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\error;
use function Laravel\Prompts\intro;
use function Laravel\Prompts\outro;

final class CheckCommand extends ProjectCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('check')
            ->setDescription('Checks the project against a highly opinionated set of coding standards.')
            ->addOption('fix', null, InputOption::VALUE_NONE, 'Automatically fix issues where possible');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->project($input);

        if ($project === null) {
            return self::FAILURE;
        }

        intro("Running baseline checks ({$project->profile()->value})...");

        $errorCount = (new CheckRunner($project, $output))->run((bool) $input->getOption('fix'));

        if ($errorCount !== 0) {
            error("Baseline check failed with {$errorCount} error(s). Run with -v or -vv for more details.");

            return self::FAILURE;
        }

        outro('Baseline check passed!');

        return self::SUCCESS;
    }
}
