<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DoesNotUseIgnitionCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return $this->checkComposerPackages('spatie/laravel-ignition')
            ? CheckResult::FAIL
            : CheckResult::PASS;
    }
}
