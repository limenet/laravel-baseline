<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesIdeHelpersCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return $this->checkComposerPackages('barryvdh/laravel-ide-helper')
        && $this->hasPostUpdateScript('ide-helper:generate')
        && $this->hasPostUpdateScript('ide-helper:meta')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
