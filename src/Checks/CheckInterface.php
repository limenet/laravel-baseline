<?php

namespace Limenet\LaravelBaseline\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;

interface CheckInterface
{
    /**
     * Get the unique name/identifier for this check.
     * Used for display and exclusion matching.
     */
    public static function name(): string;

    /**
     * Execute the check and return the result.
     */
    public function check(): CheckResult;
}
