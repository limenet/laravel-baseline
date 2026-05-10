<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesIdeHelpersCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->checkComposerPackages('barryvdh/laravel-ide-helper')) {
            return CheckResult::FAIL;
        }

        if ($this->hasPostUpdateScript('ide-helper:generate') && $this->hasPostUpdateScript('ide-helper:meta')) {
            return CheckResult::PASS;
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        $this->addToComposerScript('post-update-cmd', '@php artisan ide-helper:generate');
        $this->addToComposerScript('post-update-cmd', '@php artisan ide-helper:meta');

        return $this->fix(dry: true);
    }
}
