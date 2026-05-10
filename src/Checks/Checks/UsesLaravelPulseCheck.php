<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesLaravelPulseCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/pulse')) {
            return CheckResult::FAIL;
        }

        // Schedule entry cannot be auto-fixed
        if (!$this->hasScheduleEntry('pulse:trim')) {
            return CheckResult::FAIL;
        }

        if (!$this->checkPhpunitEnvVar('PULSE_ENABLED', 'false')) {
            $this->addComment('Missing or incorrect environment variable in phpunit.xml: Add <env name="PULSE_ENABLED" value="false"/> to <php> section');

            if ($dry) {
                return CheckResult::FAIL;
            }

            $this->setPhpunitEnvVar('PULSE_ENABLED', 'false');

            return $this->fix(dry: true);
        }

        return CheckResult::PASS;
    }
}
