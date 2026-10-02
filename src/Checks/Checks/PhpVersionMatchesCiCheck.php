<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

class PhpVersionMatchesCiCheck extends AbstractCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

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

        // A tagged value (`!reference`) is not a version this check can compare.
        if (!is_scalar($ciPhpVersion)) {
            $this->addComment('Missing PHP_VERSION variable in .gitlab-ci.yml: Add "PHP_VERSION" to the variables section');

            return CheckResult::FAIL;
        }

        // Ensure CI PHP version matches the composer constraint (both should be in format X.Y)
        if ($composerPhpVersion !== (string) $ciPhpVersion) {
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
