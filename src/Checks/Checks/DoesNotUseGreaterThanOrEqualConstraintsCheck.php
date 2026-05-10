<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DoesNotUseGreaterThanOrEqualConstraintsCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return CheckResult::FAIL;
        }

        $violations = [];

        foreach (['require', 'require-dev'] as $section) {
            foreach ($composerJson[$section] ?? [] as $package => $constraint) {
                if (str_starts_with($constraint, '>=')) {
                    $violations[] = [$section, $package, $constraint];
                }
            }
        }

        if ($violations === []) {
            return CheckResult::PASS;
        }

        $this->addComment('Disallowed ">=" version constraints in composer.json: '.implode(', ', array_map(
            fn (array $v): string => "{$v[1]}: {$v[2]}",
            $violations,
        )).'. Use "^" instead for safer dependency management.');

        if ($dry) {
            return CheckResult::FAIL;
        }

        foreach ($violations as [$section, $package]) {
            $composerJson[$section][$package] = '^'.ltrim($composerJson[$section][$package], '>= ');
        }

        $this->writeComposerJson($composerJson);

        return $this->fix(dry: true);
    }
}
