<?php

namespace Limenet\LaravelBaseline\Runner;

use Limenet\LaravelBaseline\Checks\CheckInterface;
use Limenet\LaravelBaseline\Checks\CheckRegistry;
use Limenet\LaravelBaseline\Checks\CommentCollector;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Checks\PeriodicCheckInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Project;
use Limenet\LaravelBaseline\Support\CheckName;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * One baseline run over a project, shared by the artisan command and the
 * standalone CLI so both report — and fix — identically.
 */
final class CheckRunner
{
    public function __construct(
        private readonly Project $project,
        private readonly OutputInterface $output,
    ) {}

    /**
     * Runs every applicable, non-excluded check and returns the number that failed.
     */
    public function run(bool $fix = false): int
    {
        $collector = new CommentCollector;
        $excludes = $this->project->state()->excludes();
        $errorCount = 0;

        foreach (CheckRegistry::createAll($collector, $this->project) as $check) {
            if (in_array($check::name(), $excludes, true)) {
                $this->output->writeln(sprintf('⚪ %s (excluded)', CheckName::display($check::name())));

                continue;
            }

            if ($check instanceof PeriodicCheckInterface && !$check->isApplicable()) {
                continue;
            }

            if ($this->runCheck($check, $collector, $fix)->isError()) {
                $errorCount++;
            }
        }

        return $errorCount;
    }

    private function runCheck(CheckInterface $check, CommentCollector $collector, bool $fix): CheckResult
    {
        $collector->reset();

        $wasFixed = false;

        if ($fix && $check instanceof FixableInterface) {
            $result = $check->check();

            if ($result->isError()) {
                $collector->reset();
                $result = $check->fix();
                $wasFixed = !$result->isError();
            }
        } else {
            $result = $check->check();
        }

        $hasOutput = $wasFixed || $result->isError() || $this->output->isVerbose();
        $hasComments = $result->isError() || $this->output->isVeryVerbose();

        if ($hasOutput) {
            $this->output->writeln('');
            $this->output->writeln(sprintf(
                '%s %s%s',
                $wasFixed ? '🔧' : $result->icon(),
                CheckName::display($check::name()),
                $wasFixed ? ' (fixed)' : '',
            ));
        }

        if ($hasComments) {
            foreach ($collector->all() as $comment) {
                $this->output->writeln("<comment>{$comment}</comment>");
            }
        }

        if ($hasOutput) {
            if ($result->isError()) {
                $this->output->writeln(sprintf(
                    '  💡 To exclude, add <info>%s</info> to %s',
                    $check::name(),
                    $this->project->state()->excludeHint(),
                ));
            }

            $this->output->writeln('');
        }

        return $result;
    }
}
