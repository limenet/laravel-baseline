<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesLarastanCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return $this->checkComposerPackages('larastan/larastan')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
