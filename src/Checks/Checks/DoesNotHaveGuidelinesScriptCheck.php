<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class DoesNotHaveGuidelinesScriptCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return CheckResult::PASS;
        }

        $scripts = $composerJson['scripts']['post-update-cmd'] ?? [];
        $filtered = array_values(array_filter($scripts, fn (string $s): bool => !str_contains($s, 'limenet:laravel-baseline:guidelines')));

        if (count($filtered) === count($scripts)) {
            return CheckResult::PASS;
        }

        $this->addComment('Remove "php artisan limenet:laravel-baseline:guidelines" from post-update-cmd in composer.json — the command was removed in v2.1.0 and no longer exists');

        if ($dry) {
            return CheckResult::FAIL;
        }

        $composerJson['scripts']['post-update-cmd'] = $filtered;
        $this->writeComposerJson($composerJson);

        return CheckResult::PASS;
    }
}
