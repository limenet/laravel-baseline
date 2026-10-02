<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

class IsInstalledAsRegularDependencyCheck extends AbstractFixableCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

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
