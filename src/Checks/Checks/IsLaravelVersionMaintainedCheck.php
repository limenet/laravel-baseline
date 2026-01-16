<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class IsLaravelVersionMaintainedCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return str(app()->version())->before('.')->toInteger() >= 11 ? CheckResult::PASS : CheckResult::FAIL;
    }
}
