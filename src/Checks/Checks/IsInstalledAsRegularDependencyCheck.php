<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class IsInstalledAsRegularDependencyCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return CheckResult::FAIL;
        }

        if (isset($composerJson['require-dev']['limenet/laravel-baseline'])) {
            $this->addComment('limenet/laravel-baseline is in require-dev: Move it to require in composer.json');

            return CheckResult::FAIL;
        }

        if (!isset($composerJson['require']['limenet/laravel-baseline'])) {
            $this->addComment('limenet/laravel-baseline is not installed: Add it to require in composer.json');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
