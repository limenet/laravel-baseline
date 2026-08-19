<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesIdeHelpersCheck extends AbstractFixableCheck
{
    private const GENERATED_FILES = [
        '_ide_helper.php',
        '_ide_helper_models.php',
        '.phpstorm.meta.php',
    ];

    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->checkComposerPackages('barryvdh/laravel-ide-helper')) {
            return CheckResult::FAIL;
        }

        $hasGenerate = $this->hasPostUpdateScript('ide-helper:generate');
        $hasModels = $this->hasPostUpdateScript('ide-helper:models');
        $hasMeta = $this->hasPostUpdateScript('ide-helper:meta');

        if (!$hasGenerate || !$hasModels || !$hasMeta) {
            if ($dry) {
                return CheckResult::FAIL;
            }

            if (!$hasGenerate) {
                $this->addToComposerScript('post-update-cmd', '@php artisan ide-helper:generate');
            }

            if (!$hasModels) {
                $this->addToComposerScript('post-update-cmd', '@php artisan ide-helper:models --nowrite', insertBefore: 'ide-helper:meta');
            }

            if (!$hasMeta) {
                $this->addToComposerScript('post-update-cmd', '@php artisan ide-helper:meta');
            }
        }

        foreach (self::GENERATED_FILES as $entry) {
            $gitignoreResult = $this->ensureGitignoreEntry($entry, 'ignore generated IDE Helper files', $dry);

            if ($gitignoreResult !== null && $dry) {
                return $gitignoreResult;
            }
        }

        return CheckResult::PASS;
    }
}
