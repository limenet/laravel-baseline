<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class HasNpmScriptsCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $packageJson = $this->getPackageJson();

        if ($packageJson === null) {
            return CheckResult::FAIL;
        }

        // Check if ci-lint npm script exists
        if (!isset($packageJson['scripts']['ci-lint'])) {
            $this->addComment('Missing ci-lint script in package.json: Add "ci-lint" to scripts section');

            return CheckResult::FAIL;
        }

        // Check if production npm script exists
        if (!isset($packageJson['scripts']['production'])) {
            $this->addComment('Missing production script in package.json: Add "production" to scripts section');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
