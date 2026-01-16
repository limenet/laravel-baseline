<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesRectorCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return $this->checkComposerPackages([
            'rector/rector',
            'driftingly/rector-laravel',
        ])
        && $this->checkComposerScript('ci-lint', 'rector')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
