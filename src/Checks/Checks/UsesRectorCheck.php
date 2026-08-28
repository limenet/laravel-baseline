<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesRectorCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->checkComposerPackages(['rector/rector', 'driftingly/rector-laravel'])) {
            return CheckResult::FAIL;
        }

        if ($this->composerPackageAllowsBelow('driftingly/rector-laravel', self::MIN_RECTOR_LARAVEL)) {
            $this->addComment('driftingly/rector-laravel constraint is too low: require "^'.self::MIN_RECTOR_LARAVEL.'" in composer.json — the rector.php this package writes targets the 2.6 API, where LaravelSetProvider is gone and its rules arrive through LaravelSetList::COMPOSER_BASED instead');

            return CheckResult::FAIL;
        }

        if ($this->checkComposerScript('ci-lint', 'rector')) {
            return CheckResult::PASS;
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        $this->addToComposerScript('ci-lint', '@php vendor/bin/rector --dry-run');

        return $this->fix(dry: true);
    }
}
