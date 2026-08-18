<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class IsCiLintCompleteCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        foreach ($this->policy()->strings('ciLint.required.php') as $required) {
            if (!$this->checkComposerScript('ci-lint', $required)) {
                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }
}
