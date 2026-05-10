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
