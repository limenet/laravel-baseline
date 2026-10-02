<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

class UsesPhpstanExtensionsCheck extends AbstractCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function check(): CheckResult
    {
        return $this->checkComposerPackages([
            'phpstan/extension-installer',
            'phpstan/phpstan-deprecation-rules',
            'phpstan/phpstan-strict-rules',
        ])
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
