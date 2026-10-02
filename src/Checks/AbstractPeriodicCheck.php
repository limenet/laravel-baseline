<?php

namespace Limenet\LaravelBaseline\Checks;

use Carbon\Carbon;
use Carbon\CarbonInterval;
use Limenet\LaravelBaseline\Enums\CheckResult;

abstract class AbstractPeriodicCheck extends AbstractCheck implements PeriodicCheckInterface
{
    public function interval(): CarbonInterval
    {
        return CarbonInterval::days($this->policy()->int('periodic.defaultIntervalDays'));
    }

    public function isApplicable(): bool
    {
        return true;
    }

    final public function check(): CheckResult
    {
        $lastRun = $this->project->state()->lastRun(static::name());

        if ($lastRun === null || Carbon::instance($lastRun)->add($this->interval())->isPast()) {
            $this->addComment(sprintf(
                'Run `%s` to complete this periodic check',
                $this->policy()->string('baseline.runner.'.$this->profile()->runner().'.periodicCommand'),
            ));

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
