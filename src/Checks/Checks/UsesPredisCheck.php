<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesPredisCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return $this->checkComposerPackages('predis/predis')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
