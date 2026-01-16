<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Finder\Finder;

class HasEncryptedEnvFileCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return (new Finder())
            ->in(base_path())
            ->ignoreDotFiles(false)
            ->name('.env.*.encrypted')
            ->depth('== 0')
            ->hasResults()
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
