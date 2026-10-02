<?php

namespace Limenet\LaravelBaseline\Commands;

use Illuminate\Console\Command;
use Limenet\LaravelBaseline\Project\LaravelProject;
use Limenet\LaravelBaseline\Runner\PeriodicRunner;

class PeriodicCheckCommand extends Command
{
    public $signature = 'limenet:laravel-baseline:periodic';

    public $description = 'Guides through expired periodic checks and records confirmations.';

    public function handle(): int
    {
        (new PeriodicRunner(new LaravelProject, $this->getOutput()))->run();

        return Command::SUCCESS;
    }
}
