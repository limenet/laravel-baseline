<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class CallsBaselineCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return $this->hasPostUpdateScript('limenet:laravel-baseline:check')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
