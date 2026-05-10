<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesLaravelHorizonCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/horizon')) {
            return CheckResult::FAIL;
        }

        if (!$this->hasPostDeployScript('horizon:terminate')) {
            if ($dry) {
                return CheckResult::FAIL;
            }

            $this->addToComposerScript('ci-deploy-post', '@php artisan horizon:terminate');
        }

        // Schedule entry cannot be auto-fixed
        if (!$this->hasScheduleEntry('horizon:snapshot')) {
            return CheckResult::FAIL;
        }

        return $dry ? CheckResult::PASS : $this->fix(dry: true);
    }
}
