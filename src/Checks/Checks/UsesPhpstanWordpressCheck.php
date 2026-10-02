<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

/**
 * Without the WordPress stubs PHPStan knows none of WordPress's functions,
 * classes or hooks, so a theme or plugin is either drowned in errors or
 * analysed against an ever-growing baseline.
 */
class UsesPhpstanWordpressCheck extends AbstractCheck
{
    private const PACKAGE = 'szepeviktor/phpstan-wordpress';

    public static function profiles(): array
    {
        return [Profile::WordPress];
    }

    public function check(): CheckResult
    {
        if ($this->project->hasComposerPackage(self::PACKAGE)) {
            return CheckResult::PASS;
        }

        $this->addComment('Install '.self::PACKAGE.': run `ddev composer require --dev '.self::PACKAGE.'` (phpstan/extension-installer loads it)');

        return CheckResult::FAIL;
    }
}
