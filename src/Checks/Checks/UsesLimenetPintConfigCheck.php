<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesLimenetPintConfigCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return $this->checkComposerPackages('limenet/laravel-pint-config')
        && $this->hasPostUpdateScript('laravel-pint-config:publish')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
