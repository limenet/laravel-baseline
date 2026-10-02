<?php

namespace Limenet\LaravelBaseline\Checks;

use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

interface CheckInterface
{
    /**
     * Get the unique name/identifier for this check.
     * Used for display and exclusion matching.
     */
    public static function name(): string;

    /**
     * The project profiles this check means something in. A run only creates
     * the checks whose profiles include the project's.
     *
     * @return list<Profile>
     */
    public static function profiles(): array;

    /**
     * Execute the check and return the result.
     */
    public function check(): CheckResult;
}
