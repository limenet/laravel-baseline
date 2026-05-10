<?php

namespace Limenet\LaravelBaseline\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;

interface FixableInterface
{
    /**
     * Check (dry=true) or fix (dry=false) the issue.
     * check() on every fixable check delegates to fix(dry: true).
     */
    public function fix(bool $dry = false): CheckResult;
}
