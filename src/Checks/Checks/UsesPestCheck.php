<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesPestCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return $this->checkComposerPackages(['pestphp/pest', 'pestphp/pest-plugin-laravel'])
        && !$this->checkComposerPackages(['pestphp/pest-plugin-drift', 'spatie/phpunit-watcher'])
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
