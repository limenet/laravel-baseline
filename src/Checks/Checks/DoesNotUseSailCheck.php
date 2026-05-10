<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DoesNotUseSailCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        // Package presence can't be auto-fixed — requires composer remove
        if ($this->checkComposerPackages('laravel/sail')) {
            return CheckResult::FAIL;
        }

        if (!file_exists(base_path('docker-compose.yml'))) {
            return CheckResult::PASS;
        }

        $this->addComment('docker-compose.yml file should be removed from project root');

        if ($dry) {
            return CheckResult::FAIL;
        }

        unlink(base_path('docker-compose.yml'));

        return $this->fix(dry: true);
    }
}
