<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

class UsesPestPhpstanPluginCheck extends AbstractCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function check(): CheckResult
    {
        if (!$this->composerPackageSatisfies('pestphp/pest', '>=5.0') || !$this->checkComposerPackages('phpstan/phpstan')) {
            return CheckResult::WARN;
        }

        return $this->checkComposerPackages('pestphp/pest-plugin-phpstan')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
