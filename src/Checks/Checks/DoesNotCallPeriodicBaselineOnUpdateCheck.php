<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DoesNotCallPeriodicBaselineOnUpdateCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if ($this->hasPostUpdateScript('limenet:laravel-baseline:periodic')) {
            $this->addComment('Remove `php artisan limenet:laravel-baseline:periodic` from post-update-cmd in composer.json — periodic checks fail CI automatically when expired, so running it on every composer update is unnecessary');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
