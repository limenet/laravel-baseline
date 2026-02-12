<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class PhpVersionMatchesCiCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $composerPhpVersion = $this->getComposerPhpVersion();

        if ($composerPhpVersion === null) {
            return CheckResult::FAIL;
        }

        $ciData = $this->getGitlabCiData();

        if ($ciData === null) {
            return CheckResult::FAIL;
        }

        $ciPhpVersion = $ciData['variables']['PHP_VERSION'] ?? null;

        if ($ciPhpVersion === null) {
            $this->addComment('Missing PHP_VERSION variable in .gitlab-ci.yml: Add "PHP_VERSION" to the variables section');

            return CheckResult::FAIL;
        }

        // Ensure CI PHP version matches the composer constraint (both should be in format X.Y)
        if ($composerPhpVersion !== $ciPhpVersion) {
            $this->addComment(sprintf(
                'PHP version mismatch: composer.json requires ^%s but .gitlab-ci.yml uses %s',
                $composerPhpVersion,
                $ciPhpVersion,
            ));

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
