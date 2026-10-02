<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractPeriodicCheck;
use Limenet\LaravelBaseline\Project\Profile;

class UpdatesDependenciesCheck extends AbstractPeriodicCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function promptDescription(): string
    {
        return 'Run the `updating-dependencies` skill to update composer & npm dependencies, review changelogs, and check for semver-blocked majors.';
    }
}
