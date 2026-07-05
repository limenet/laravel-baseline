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

        if (!$this->hasPostUpdateScript('ide-helper:generate') || !$this->hasPostUpdateScript('ide-helper:models') || !$this->hasPostUpdateScript('ide-helper:meta')) {
            if ($dry) {
                return CheckResult::FAIL;
            }

            $this->addToComposerScript('post-update-cmd', '@php artisan ide-helper:generate');
            $this->addToComposerScript('post-update-cmd', '@php artisan ide-helper:models --nowrite');
            $this->addToComposerScript('post-update-cmd', '@php artisan ide-helper:meta');

            return $this->fix(dry: true);
        }

        foreach (self::GENERATED_FILES as $entry) {
            $gitignoreResult = $this->ensureGitignoreEntry($entry, $dry);

            if ($gitignoreResult !== null && $dry) {
                return $gitignoreResult;
            }
        }

        return CheckResult::PASS;
    }

    private function ensureGitignoreEntry(string $entry, bool $dry): ?CheckResult
    {
        $file = base_path('.gitignore');

        if (!file_exists($file)) {
            $this->addComment("Missing .gitignore in project root: create it and add '{$entry}'");

            if ($dry) {
                return CheckResult::FAIL;
            }

            file_put_contents($file, $entry."\n");

            return CheckResult::FAIL;
        }

        $contents = (string) file_get_contents($file);
        $lines = array_map('trim', explode("\n", $contents));
        $normalizedEntry = trim($entry, '/');
        $normalizedLines = array_map(static fn (string $line): string => trim($line, '/'), $lines);

        if (in_array($normalizedEntry, $normalizedLines, true)) {
            return null;
        }

        $this->addComment("Missing entry in .gitignore: add '{$entry}' to ignore generated IDE Helper files");

        if ($dry) {
            return CheckResult::FAIL;
        }

        $prefix = ($contents === '' || str_ends_with($contents, "\n")) ? '' : "\n";
        file_put_contents($file, $contents.$prefix.$entry."\n");

        return CheckResult::FAIL;
    }
}
