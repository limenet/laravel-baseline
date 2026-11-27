<?php

namespace Limenet\LaravelBaseline\Concerns;

use Illuminate\Support\Facades\Schedule;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Schedule\EnvironmentGuardedScheduleFinder;

/**
 * Reads the booted application's console schedule, so only checks that run
 * inside Laravel may use it — the standalone runner has no application.
 *
 * An entry the current environment does not register (wrapped in
 * `if (app()->isProduction())` and the like) is found by parsing the scheduler
 * sources instead; see EnvironmentGuardedScheduleFinder.
 */
trait InteractsWithLaravelSchedule
{
    protected function hasScheduleEntry(string $command): bool
    {
        $this->addComment('Schedule check: '.$command);

        foreach (Schedule::events() as $event) {
            if (str_contains($event->command ?? '', $command)) {
                return true;
            }
        }

        return (new EnvironmentGuardedScheduleFinder)->contains(
            [
                $this->path('routes/console.php'),
                $this->path('bootstrap/app.php'),
                $this->path('app/Console/Kernel.php'),
            ],
            $command,
        );
    }

    /**
     * Checks if a package is installed and has required schedule entries
     *
     * @param  string|list<string>  $scheduleCommands
     */
    protected function checkPackageWithSchedule(
        string $package,
        string|array $scheduleCommands,
    ): CheckResult {
        if (!$this->checkComposerPackages($package)) {
            return CheckResult::WARN;
        }

        $commands = is_string($scheduleCommands) ? [$scheduleCommands] : $scheduleCommands;

        foreach ($commands as $command) {
            if (!$this->hasScheduleEntry($command)) {
                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }
}
