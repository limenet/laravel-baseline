<?php

namespace Limenet\LaravelBaseline\Runner;

use DateTimeImmutable;
use Limenet\LaravelBaseline\Checks\CheckRegistry;
use Limenet\LaravelBaseline\Checks\CommentCollector;
use Limenet\LaravelBaseline\Checks\PeriodicCheckInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Project;
use Limenet\LaravelBaseline\Support\CheckName;
use Symfony\Component\Console\Output\OutputInterface;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\info;

/**
 * Walks the developer through every expired periodic check and records the
 * ones they confirm, shared by the artisan command and the standalone CLI.
 */
final class PeriodicRunner
{
    public function __construct(
        private readonly Project $project,
        private readonly OutputInterface $output,
    ) {}

    public function run(): void
    {
        $collector = new CommentCollector;

        $expired = array_filter(
            CheckRegistry::createAll($collector, $this->project),
            static fn ($check): bool => $check instanceof PeriodicCheckInterface
                && $check->isApplicable()
                && $check->check() === CheckResult::FAIL,
        );

        if ($expired === []) {
            info('All periodic checks are up to date!');

            return;
        }

        /** @var PeriodicCheckInterface $check */
        foreach ($expired as $check) {
            $this->output->writeln('');
            $this->output->writeln(sprintf('<info>%s</info>', CheckName::display($check::name())));
            $this->output->writeln($check->promptDescription());
            $this->output->writeln('');

            if (confirm('Have you completed this task?', default: false)) {
                $this->project->state()->recordRun($check::name(), new DateTimeImmutable);
                $this->output->writeln('✅ Marked as done.');
            } else {
                $this->output->writeln('⏭ Skipped.');
            }
        }
    }
}
