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

        return $this->hasPostDeployScript('horizon:terminate')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
