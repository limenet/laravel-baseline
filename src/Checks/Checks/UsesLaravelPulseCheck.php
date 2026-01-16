<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesLaravelPulseCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/pulse')) {
            return CheckResult::FAIL;
        }

        if (!$this->hasScheduleEntry('pulse:trim')) {
            return CheckResult::FAIL;
        }

        if (!$this->checkPhpunitEnvVar('PULSE_ENABLED', 'false')) {
            $this->addComment('Missing or incorrect environment variable in phpunit.xml: Add <env name="PULSE_ENABLED" value="false"/> to <php> section');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
