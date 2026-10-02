<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Checks\CheckRegistry;
use Limenet\LaravelBaseline\Enums\CheckResult;

/**
 * An exclude naming a check this package no longer registers silences nothing:
 * the name is matched against the registry, so once a check is renamed or
 * removed the entry is dead config that outlives the reason it was added.
 */
class DoesNotExcludeUnknownChecksCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        // Read the file rather than the excludes a run honours: the fix rewrites
        // it, and Laravel's cached config would report state it no longer holds.
        $state = $this->project->state();
        $excludes = $state->storedExcludes();

        if ($excludes === []) {
            return CheckResult::PASS;
        }

        $known = array_map(
            fn (string $class): string => $class::name(),
            CheckRegistry::all(),
        );

        $live = [];
        $dead = [];

        foreach ($excludes as $name) {
            if (is_string($name) && in_array($name, $known, true)) {
                $live[] = $name;

                continue;
            }

            $dead[] = is_string($name) ? $name : get_debug_type($name);
        }

        if ($dead === []) {
            return CheckResult::PASS;
        }

        foreach ($dead as $name) {
            $this->addComment(sprintf(
                'Remove "%s" from the excludes in %s: no check by that name is registered, so the entry excludes nothing',
                $name,
                $state->location(),
            ));
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        $state->setExcludes($live);

        return $this->fix(dry: true);
    }
}
