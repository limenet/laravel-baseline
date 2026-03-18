<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesLaravelHorizonCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/horizon')) {
            return CheckResult::FAIL;
        }

        if (!$this->hasPostDeployScript('horizon:terminate')) {
            return CheckResult::FAIL;
        }

        if (!$this->hasScheduleEntry('horizon:snapshot')) {
            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
