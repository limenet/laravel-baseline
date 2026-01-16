<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Yaml\Yaml;

class DdevMutagenIgnoresNodeModulesCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $mutagenConfigFile = base_path('.ddev/mutagen/mutagen.yml');

        if (!file_exists($mutagenConfigFile)) {
            $this->addComment('DDEV Mutagen configuration missing: Create .ddev/mutagen/mutagen.yml');

            return CheckResult::FAIL;
        }

        // Check if mutagen config is not ignored in .ddev/.gitignore
        $ddevGitignoreFile = base_path('.ddev/.gitignore');

        if (file_exists($ddevGitignoreFile)) {
            $gitignoreContent = file_get_contents($ddevGitignoreFile) ?: '';

            // Check if .gitignore is auto-generated
            if (str_contains($gitignoreContent, '#ddev-generated')) {
                $this->addComment('DDEV .gitignore is auto-generated: Remove "#ddev-generated" comment from .ddev/.gitignore to prevent DDEV from regenerating it');

                return CheckResult::FAIL;
            }

            $gitignoreLines = array_map('trim', explode("\n", $gitignoreContent));

            // Check for patterns that would ignore the gitignore file itself
            $ignoringSelf = false;
            foreach ($gitignoreLines as $line) {
                // Skip comments and empty lines
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                // Check if line matches patterns that would ignore .gitignore itself
                if ($line === '/.gitignore' || $line === '.gitignore') {
                    $ignoringSelf = true;
                    break;
                }
            }

            if ($ignoringSelf) {
                $this->addComment('DDEV .gitignore is ignoring itself: Remove "/.gitignore" from .ddev/.gitignore to track the gitignore file');

                return CheckResult::FAIL;
            }

            // Check for patterns that would ignore mutagen.yml
            $ignoringMutagen = false;
            foreach ($gitignoreLines as $line) {
                // Skip comments and empty lines
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                // Check if line matches patterns that would ignore mutagen.yml
                if ($line === '/mutagen/mutagen.yml' || $line === 'mutagen/mutagen.yml' || $line === '/mutagen/' || $line === 'mutagen/') {
                    $ignoringMutagen = true;
                    break;
                }
            }

            if ($ignoringMutagen) {
                $this->addComment('DDEV Mutagen configuration is ignored by git: Remove "/mutagen/mutagen.yml" from .ddev/.gitignore to track the configuration');

                return CheckResult::FAIL;
            }
        }

        // Check if the file contains #ddev-generated comment
        $mutagenConfigContent = file_get_contents($mutagenConfigFile) ?: '';

        if (str_contains($mutagenConfigContent, '#ddev-generated')) {
            $this->addComment('DDEV Mutagen configuration is auto-generated: Remove "#ddev-generated" comment from .ddev/mutagen/mutagen.yml to prevent DDEV from overwriting your changes');

            return CheckResult::FAIL;
        }

        $mutagenConfig = Yaml::parseFile($mutagenConfigFile);

        // Check if sync.defaults.ignore.paths exists and contains "/node_modules"
        $ignorePaths = $mutagenConfig['sync']['defaults']['ignore']['paths'] ?? [];

        if (!in_array('/node_modules', $ignorePaths, true)) {
            $this->addComment('DDEV Mutagen configuration incomplete: Add "/node_modules" to sync.defaults.ignore.paths in .ddev/mutagen/mutagen.yml and run "ddev mutagen reset" to apply changes');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
