<?php

namespace Limenet\LaravelBaseline\Cli\Command;

use Limenet\LaravelBaseline\Runner\PeriodicRunner;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class PeriodicCommand extends ProjectCommand
{
    protected function configure(): void
    {
        parent::configure();

        $this
            ->setName('periodic')
            ->setDescription('Guides through expired periodic checks and records confirmations.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $project = $this->project($input);

        if ($project === null) {
            return self::FAILURE;
        }

        (new PeriodicRunner($project, $output))->run();

        return self::SUCCESS;
    }
}
