<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

class UsesRectorCheck extends AbstractFixableCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function fix(bool $dry = false): CheckResult
    {
        // rector-laravel and the floor its 2.6 API needs only concern the
        // Laravel rector.php this package writes; elsewhere plain Rector is it.
        $isLaravel = $this->profile() === Profile::Laravel;

        if (!$this->checkComposerPackages($isLaravel ? ['rector/rector', 'driftingly/rector-laravel'] : ['rector/rector'])) {
            return CheckResult::FAIL;
        }

        if ($isLaravel && $this->composerPackageAllowsBelow('driftingly/rector-laravel', self::MIN_RECTOR_LARAVEL)) {
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
