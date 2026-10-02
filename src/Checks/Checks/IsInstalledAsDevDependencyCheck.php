<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

/**
 * Outside Laravel the package has no runtime role — vendor/bin/baseline is a
 * development tool — so it belongs in require-dev, where a `--no-dev` deploy
 * (a theme copied under a public web root, say) leaves it and its tooling out.
 */
class IsInstalledAsDevDependencyCheck extends AbstractFixableCheck
{
    public static function profiles(): array
    {
        return [Profile::Php, Profile::WordPress];
    }

    public function fix(bool $dry = false): CheckResult
    {
        $package = $this->policy()->string('baseline.composerPackage');
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return CheckResult::FAIL;
        }

        if (isset($composerJson['require'][$package])) {
            $this->addComment("{$package} is in require: Move it to require-dev in composer.json — it is only used during development");

            if ($dry) {
                return CheckResult::FAIL;
            }

            $version = $composerJson['require'][$package];
            unset($composerJson['require'][$package]);
            $composerJson['require-dev'][$package] = $version;
            $this->writeComposerJson($composerJson);

            return $this->fix(dry: true);
        }

        if (!isset($composerJson['require-dev'][$package])) {
            $this->addComment("{$package} is not installed: Add it to require-dev in composer.json");

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
