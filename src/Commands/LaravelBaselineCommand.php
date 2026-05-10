<?php

namespace Limenet\LaravelBaseline\Commands;

use Illuminate\Console\Command;
use Limenet\LaravelBaseline\Checks\CheckInterface;
use Limenet\LaravelBaseline\Checks\CheckRegistry;
use Limenet\LaravelBaseline\Checks\CommentCollector;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Checks\PeriodicCheckInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

class LaravelBaselineCommand extends Command
{
    public $signature = 'limenet:laravel-baseline:check {--fix : Automatically fix issues where possible}';

    public $description = 'Checks the project against a highly opinionated set of coding standards.';

    public function handle(): int
    {
        $collector = new CommentCollector();
        $fix = (bool) $this->option('fix');

        $results = collect(CheckRegistry::createAll($collector))
            ->reject(fn (CheckInterface $check): bool => $this->isExcluded($check))
            ->reject(fn (CheckInterface $check): bool => $check instanceof PeriodicCheckInterface && !$check->isApplicable())
            ->map(fn (CheckInterface $check): CheckResult => $this->runCheck($check, $collector, $fix));

        $errorCount = $results
            ->filter(fn (CheckResult $result): bool => $result->isError())
            ->count();

        if ($errorCount !== 0) {
            $this->error(
                "Baseline check failed with {$errorCount} error(s). Run with -v or -vv for more details.",
            );

            return Command::FAILURE;
        }

        $this->info('Baseline check passed!');

        return Command::SUCCESS;
    }

    private function isExcluded(CheckInterface $check): bool
    {
        $name = $check::name();

        if (in_array($name, config('baseline.excludes', []), true)) {
            $this->line(
                sprintf(
                    '⚪ %s (excluded)',
                    str($name)->ucsplit()->implode(' '),
                ),
            );

            return true;
        }

        return false;
    }

    private function runCheck(CheckInterface $check, CommentCollector $collector, bool $fix = false): CheckResult
    {
        $collector->reset();

        $useFix = $fix && $check instanceof FixableInterface;
        $result = $useFix ? $check->fix() : $check->check();

        $checkName = $check::name();
        $displayName = str($checkName)->ucsplit()->implode(' ');

        $hasOutput = $result->isError() || $this->getOutput()->isVerbose();
        $hasComments = $result->isError() || $this->getOutput()->isVeryVerbose();

        if ($useFix && !$result->isError()) {
            $hasOutput = true;
        }

        if ($hasOutput) {
            $this->newLine();
            $icon = ($useFix && !$result->isError()) ? '🔧' : $result->icon();
            $suffix = ($useFix && !$result->isError()) ? ' (fixed)' : '';
            $this->line(sprintf(
                '%s %s%s',
                $icon,
                $displayName,
                $suffix,
            ));
        }

        if ($hasComments) {
            collect($collector->all())
                ->each(fn (string $comment) => $this->comment($comment));
        }

        if ($hasOutput) {
            if ($result->isError()) {
                $this->line(
                    sprintf(
                        '  💡 To exclude, add <info>%s</info> to the <info>baseline.excludes</info> config',
                        $checkName,
                    ),
                );
            }

            $this->newLine();
        }

        return $result;
    }
}
