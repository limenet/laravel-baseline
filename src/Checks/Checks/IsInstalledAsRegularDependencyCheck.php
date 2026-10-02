<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

/**
 * A Laravel app loads the package at runtime (its Spatie Health checks, the
 * service provider), so it must survive `composer install --no-dev`. Outside
 * Laravel it is pure tooling — see IsInstalledAsDevDependencyCheck.
 */
class IsInstalledAsRegularDependencyCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return CheckResult::FAIL;
        }

        if (isset($composerJson['require-dev']['limenet/laravel-baseline'])) {
            $this->addComment('limenet/laravel-baseline is in require-dev: Move it to require in composer.json');

            if ($dry) {
                return CheckResult::FAIL;
            }

            $version = $composerJson['require-dev']['limenet/laravel-baseline'];
            unset($composerJson['require-dev']['limenet/laravel-baseline']);
            $composerJson['require']['limenet/laravel-baseline'] = $version;
            $this->writeComposerJson($composerJson);

            return $this->fix(dry: true);
        }

        if (!isset($composerJson['require']['limenet/laravel-baseline'])) {
            $this->addComment('limenet/laravel-baseline is not installed: Add it to require in composer.json');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
