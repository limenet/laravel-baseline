<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class IsCiLintCompleteCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return $this->checkComposerScript('ci-lint', 'pint --parallel')
        && $this->checkComposerScript('ci-lint', 'phpstan')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
