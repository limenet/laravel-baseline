<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DoesNotUseGreaterThanOrEqualConstraintsCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return CheckResult::FAIL;
        }

        $violations = [];

        foreach (['require', 'require-dev'] as $section) {
            foreach ($composerJson[$section] ?? [] as $package => $constraint) {
                if (str_starts_with($constraint, '>=')) {
                    $violations[] = "{$package}: {$constraint}";
                }
            }
        }

        if ($violations !== []) {
            $this->addComment('Disallowed ">=" version constraints in composer.json: '.implode(', ', $violations).'. Use "^" instead for safer dependency management.');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
