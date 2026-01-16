<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesLaravelPennantCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/pennant')) {
            return CheckResult::WARN;
        }

        return $this->hasPostDeployScript('pennant:purge')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
