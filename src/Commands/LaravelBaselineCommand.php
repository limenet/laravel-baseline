<?php

namespace Limenet\LaravelBaseline\Commands;

use Illuminate\Console\Command;
use Limenet\LaravelBaseline\Checks\CheckInterface;
use Limenet\LaravelBaseline\Checks\CheckRegistry;
use Limenet\LaravelBaseline\Checks\CommentCollector;
use Limenet\LaravelBaseline\Enums\CheckResult;

class LaravelBaselineCommand extends Command
{
    public $signature = 'limenet:laravel-baseline:check';

    public $description = 'Checks the project against a highly opinionated set of coding standards.';

    public function handle(): int
    {
        $collector = new CommentCollector();

        $errorCount = collect(CheckRegistry::createAll($collector))
            ->reject(fn (CheckInterface $check): bool => $this->isExcluded($check))
            ->map(fn (CheckInterface $check): CheckResult => $this->runCheck($check, $collector))
            ->filter(fn (CheckResult $result): bool => $result->isError())
            ->count();

        if ($errorCount !== 0) {
            $this->error("Baseline check failed with {$errorCount} error(s). Run with -v or -vv for more details.");

            return Command::FAILURE;
        }

        $this->info('Baseline check passed!');

        return Command::SUCCESS;
    }

    private function isExcluded(CheckInterface $check): bool
    {
        $name = $check::name();

        if (in_array($name, config('baseline.excludes', []), true)) {
            $this->line(sprintf('⚪ %s (excluded)', str($name)->ucsplit()->implode(' ')));

            return true;
        }

        return false;
    }

    private function runCheck(CheckInterface $check, CommentCollector $collector): CheckResult
    {
        $collector->reset();
        $result = $check->check();
        $checkName = $check::name();
        $displayName = str($checkName)->ucsplit()->implode(' ');

        $hasOutput = $result->isError() || $this->getOutput()->isVerbose();
        $hasComments = $result->isError() || $this->getOutput()->isVeryVerbose();

        if ($hasOutput) {
            $this->newLine();
            $this->line(sprintf('%s %s', $result->icon(), $displayName));
        }

        if ($hasComments) {
            collect($collector->all())->each(fn (string $comment) => $this->comment($comment));
        }

        if ($hasOutput) {
            $this->line(sprintf('  💡 To exclude, add <info>%s</info> to the <info>baseline.excludes</info> config', $checkName));
            $this->newLine();
        }

        return $result;
    }
}
