<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesLaravelTelescopeCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/telescope')) {
            return CheckResult::FAIL;
        }

        if (!$this->hasPostUpdateScript('telescope:publish')) {
            if ($dry) {
                return CheckResult::FAIL;
            }

            $this->addToComposerScript('post-update-cmd', '@php artisan telescope:publish --ansi');
        }

        // Schedule entry cannot be auto-fixed
        if (!$this->hasScheduleEntry('telescope:prune')) {
            return CheckResult::FAIL;
        }

        if (!$this->checkPhpunitEnvVar('TELESCOPE_ENABLED', 'false')) {
            $this->addComment('Missing or incorrect environment variable in phpunit.xml: Add <env name="TELESCOPE_ENABLED" value="false"/> to <php> section');

            if ($dry) {
                return CheckResult::FAIL;
            }

            $this->setPhpunitEnvVar('TELESCOPE_ENABLED', 'false');
        }

        return $dry ? CheckResult::PASS : $this->fix(dry: true);
    }
}
