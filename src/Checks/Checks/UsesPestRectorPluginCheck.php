<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesPestRectorPluginCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->composerPackageSatisfies('pestphp/pest', '>=5.0') || !$this->checkComposerPackages('rector/rector')) {
            return CheckResult::WARN;
        }

        return $this->checkComposerPackages('pestphp/pest-plugin-rector')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
