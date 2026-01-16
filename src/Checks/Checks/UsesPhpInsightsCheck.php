<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesPhpInsightsCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return $this->checkComposerPackages('nunomaduro/phpinsights')
        && $this->checkComposerScript('ci-lint', 'insights --summary --no-interaction')
        && $this->checkComposerScript('ci-lint', 'insights -n --ansi --format=codeclimate > codeclimate-report.json 2>/dev/null')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
