<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DoesNotUseHorizonWatcherCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return $this->checkComposerPackages('spatie/laravel-horizon-watcher')
            ? CheckResult::FAIL
            : CheckResult::PASS;
    }
}
