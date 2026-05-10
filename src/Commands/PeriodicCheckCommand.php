<?php

namespace Limenet\LaravelBaseline\Commands;

use DateTimeImmutable;
use Illuminate\Console\Command;
use Limenet\LaravelBaseline\Checks\CheckRegistry;
use Limenet\LaravelBaseline\Checks\CommentCollector;
use Limenet\LaravelBaseline\Checks\PeriodicCheckInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\State\PeriodicStateManager;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

class PeriodicCheckCommand extends Command
{
    public $signature = 'limenet:laravel-baseline:periodic';

    public $description = 'Guides through expired periodic checks and records confirmations.';

    public function handle(): int
    {
        $collector = new CommentCollector();

        $expired = collect(CheckRegistry::createAll($collector))
            ->filter(fn ($check) => $check instanceof PeriodicCheckInterface)
            ->filter(fn (PeriodicCheckInterface $check) => $check->isApplicable())
            ->filter(fn (PeriodicCheckInterface $check) => $check->check() === CheckResult::FAIL);

        if ($expired->isEmpty()) {
            info('All periodic checks are up to date!');

            return Command::SUCCESS;
        }

        foreach ($expired as $check) {
            $this->newLine();
            $this->line(sprintf('<info>%s</info>', str($check::name())->ucsplit()->implode(' ')));
            $this->line($check->promptDescription());
            $this->newLine();

            if (confirm('Have you completed this task?', default: false)) {
                PeriodicStateManager::setLastRun($check::name(), new DateTimeImmutable());
                $this->line('✅ Marked as done.');
            } else {
                $this->line('⏭ Skipped.');
            }
        }

        return Command::SUCCESS;
    }
}
