<?php

namespace Limenet\LaravelBaseline\Checks;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\State\PeriodicStateManager;

abstract class AbstractPeriodicCheck extends AbstractCheck implements PeriodicCheckInterface
{
    public function interval(): CarbonInterval
    {
        return CarbonInterval::days(30);
    }

    public function isApplicable(): bool
    {
        return true;
    }

    final public function check(): CheckResult
    {
        $lastRun = PeriodicStateManager::getLastRun(static::name());

        if ($lastRun === null || Carbon::instance($lastRun)->add($this->interval())->isPast()) {
            $this->addComment('Run `ddev artisan limenet:laravel-baseline:periodic` to complete this periodic check');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
