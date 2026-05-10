<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class CallsBaselineCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        if ($this->hasPostUpdateScript('limenet:laravel-baseline:check --fix')) {
            return CheckResult::PASS;
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        $composerJson = $this->getComposerJson();

        if ($composerJson === null) {
            return CheckResult::FAIL;
        }

        // Upgrade existing entry (without --fix) to include --fix
        $scripts = $composerJson['scripts']['post-update-cmd'] ?? [];

        foreach ($scripts as $i => $script) {
            if (str_contains($script, 'limenet:laravel-baseline:check') && !str_contains($script, '--fix')) {
                $composerJson['scripts']['post-update-cmd'][$i] = rtrim($script).' --fix';
                $this->writeComposerJson($composerJson);

                return $this->fix(dry: true);
            }
        }

        $this->addToComposerScript('post-update-cmd', '@php artisan limenet:laravel-baseline:check --fix');

        return $this->fix(dry: true);
    }
}
