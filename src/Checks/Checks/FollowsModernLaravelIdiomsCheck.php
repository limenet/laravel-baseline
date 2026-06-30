<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractPeriodicCheck;

class FollowsModernLaravelIdiomsCheck extends AbstractPeriodicCheck
{
    public function isApplicable(): bool
    {
        // Typed cache getters and BackedEnum cache/session keys require Laravel
        // 12.45+ (and exist in 13.x); skip the reminder on older versions.
        return $this->composerPackageSatisfies('laravel/framework', '>=12.45');
    }

    public function promptDescription(): string
    {
        return 'Run the `auditing-laravel-idioms` skill to confirm cache and session calls use typed getters (Cache::string(), etc.) and pass BackedEnum cases directly as keys.';
    }
}
