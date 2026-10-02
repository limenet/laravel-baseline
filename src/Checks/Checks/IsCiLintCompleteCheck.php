<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

class IsCiLintCompleteCheck extends AbstractCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function check(): CheckResult
    {
        foreach ($this->policy()->strings('ciLint.required.composer') as $required) {
            if (!$this->checkComposerScript('ci-lint', $required)) {
                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }
}
