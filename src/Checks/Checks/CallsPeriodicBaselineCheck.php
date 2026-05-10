<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class CallsPeriodicBaselineCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return $this->hasPostUpdateScript('limenet:laravel-baseline:periodic')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
