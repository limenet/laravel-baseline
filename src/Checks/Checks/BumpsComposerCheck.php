<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class BumpsComposerCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        return $this->hasPostUpdateScript('composer bump')
            ? CheckResult::PASS
            : CheckResult::FAIL;
    }
}
