<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

/**
 * A project runs one baseline runner, and in a Laravel project that is this one:
 * the npm runner is the fallback for projects the Composer package cannot reach.
 * Running both means two registries checking the same files with different
 * subsets of the standard, and two `--fix` implementations writing to
 * package.json and .claude/settings.json.
 *
 * Uninstalling is the developer's call — npm and its lockfile are not this
 * check's to rewrite — so it detects and reports.
 */
class DoesNotUseBothBaselineRunnersCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $packageJson = $this->getPackageJson();

        if ($packageJson === null) {
            return CheckResult::PASS;
        }

        $npmPackage = $this->policy()->string('baseline.npmPackage');

        foreach (['dependencies', 'devDependencies'] as $section) {
            if (!isset($packageJson[$section][$npmPackage])) {
                continue;
            }

            $composerPackage = $this->policy()->string('baseline.composerPackage');

            $this->addComment("Two baseline runners in one project: package.json requires {$npmPackage} in {$section}, but this project is already checked by {$composerPackage}, which covers everything the npm runner does and more. Run \"npm uninstall {$npmPackage}\" and drop its \"baseline check\" entry from the ci-lint npm script.");

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
