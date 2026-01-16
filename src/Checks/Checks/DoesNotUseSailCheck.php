<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DoesNotUseSailCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if ($this->checkComposerPackages('laravel/sail')) {
            return CheckResult::FAIL;
        }

        if (file_exists(base_path('docker-compose.yml'))) {
            $this->addComment('docker-compose.yml file should be removed from project root');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
