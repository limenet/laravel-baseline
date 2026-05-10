<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesPhpInsightsCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->checkComposerPackages('nunomaduro/phpinsights')) {
            return CheckResult::FAIL;
        }

        if ($this->checkComposerScript('ci-lint', 'insights --summary --no-interaction')
            && $this->checkComposerScript('ci-lint', 'insights -n --ansi --format=codeclimate > codeclimate-report.json 2>/dev/null')
        ) {
            return CheckResult::PASS;
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        $this->addToComposerScript('ci-lint', '@php artisan insights --summary --no-interaction');
        $this->addToComposerScript('ci-lint', '@php artisan insights -n --ansi --format=codeclimate > codeclimate-report.json 2>/dev/null');

        return $this->fix(dry: true);
    }
}
