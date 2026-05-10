<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DoesNotUseSpatiePasskeysWithFortifyCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/fortify')) {
            return CheckResult::PASS;
        }

        if ($this->checkComposerPackages('spatie/laravel-passkeys')) {
            $this->addComment('Remove spatie/laravel-passkeys: it overlaps with laravel/fortify which is already installed.');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
