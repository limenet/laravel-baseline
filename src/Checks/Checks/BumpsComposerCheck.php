<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class BumpsComposerCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        if ($this->hasPostUpdateScript('composer bump')) {
            return CheckResult::PASS;
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        $this->addToComposerScript('post-update-cmd', 'composer bump');

        return $this->fix(dry: true);
    }
}
