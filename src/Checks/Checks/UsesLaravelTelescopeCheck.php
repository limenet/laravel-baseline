<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesLaravelTelescopeCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/telescope')) {
            return CheckResult::FAIL;
        }

        if (!$this->hasPostUpdateScript('telescope:publish')) {
            return CheckResult::FAIL;
        }

        if (!$this->hasScheduleEntry('telescope:prune')) {
            return CheckResult::FAIL;
        }

        if (!$this->checkPhpunitEnvVar('TELESCOPE_ENABLED', 'false')) {
            $this->addComment('Missing or incorrect environment variable in phpunit.xml: Add <env name="TELESCOPE_ENABLED" value="false"/> to <php> section');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
