<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class PhpVersionMatchesDdevCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $composerPhpVersion = $this->getComposerPhpVersion();

        if ($composerPhpVersion === null) {
            return CheckResult::FAIL;
        }

        $ddevConfig = $this->getDdevConfig();

        if ($ddevConfig === null) {
            return CheckResult::FAIL;
        }

        $ddevPhpVersion = $ddevConfig['php_version'] ?? null;

        if ($ddevPhpVersion === null) {
            $this->addComment('DDEV configuration missing php_version: Add "php_version" to .ddev/config.yaml');

            return CheckResult::FAIL;
        }

        // Ensure DDEV PHP version matches the composer constraint (both should be in format X.Y)
        if ($composerPhpVersion !== $ddevPhpVersion) {
            $this->addComment(sprintf(
                'PHP version mismatch: composer.json requires ^%s but .ddev/config.yaml uses %s',
                $composerPhpVersion,
                $ddevPhpVersion,
            ));

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
