<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesLimenetPintConfigCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->checkComposerPackages('limenet/laravel-pint-config')) {
            return CheckResult::FAIL;
        }

        if ($this->hasPostUpdateScript('laravel-pint-config:publish')) {
            return CheckResult::PASS;
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        $this->addToComposerScript('post-update-cmd', '@php artisan laravel-pint-config:publish');

        return $this->fix(dry: true);
    }
}
