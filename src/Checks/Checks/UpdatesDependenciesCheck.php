<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractPeriodicCheck;

class UpdatesDependenciesCheck extends AbstractPeriodicCheck
{
    public function promptDescription(): string
    {
        return 'Run the `updating-dependencies` skill to update composer & npm dependencies, review changelogs, and check for semver-blocked majors.';
    }
}
