<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Yaml\Yaml;

class DdevMutagenIgnoresNodeModulesCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $mutagenFile = base_path('.ddev/mutagen/mutagen.yml');
        $ddevGitignore = base_path('.ddev/.gitignore');

        // Check / fix .ddev/.gitignore
        if (file_exists($ddevGitignore)) {
            $gitignoreContent = file_get_contents($ddevGitignore) ?: '';

            if (str_contains($gitignoreContent, '#ddev-generated')) {
                $this->addComment('DDEV .gitignore is auto-generated: Remove "#ddev-generated" comment from .ddev/.gitignore to prevent DDEV from regenerating it');

                if ($dry) {
                    return CheckResult::FAIL;
                }

                $gitignoreContent = ltrim(preg_replace('/^#ddev-generated.*$/m', '', $gitignoreContent) ?? $gitignoreContent);
                file_put_contents($ddevGitignore, $gitignoreContent);
            }

            $gitignoreLines = array_map('trim', explode("\n", $gitignoreContent));
            $removePatterns = ['/.gitignore', '.gitignore', '/mutagen/mutagen.yml', 'mutagen/mutagen.yml', '/mutagen/', 'mutagen/'];

            foreach ($gitignoreLines as $line) {
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                if ($line === '/.gitignore' || $line === '.gitignore') {
                    $this->addComment('DDEV .gitignore is ignoring itself: Remove "/.gitignore" from .ddev/.gitignore to track the gitignore file');

                    if ($dry) {
                        return CheckResult::FAIL;
                    }
                }

                if (in_array($line, ['/mutagen/mutagen.yml', 'mutagen/mutagen.yml', '/mutagen/', 'mutagen/'], true)) {
                    $this->addComment('DDEV Mutagen configuration is ignored by git: Remove "/mutagen/mutagen.yml" from .ddev/.gitignore to track the configuration');

                    if ($dry) {
                        return CheckResult::FAIL;
                    }
                }
            }

            if (!$dry) {
                $filtered = array_filter($gitignoreLines, fn (string $line): bool => !in_array($line, $removePatterns, true));
                file_put_contents($ddevGitignore, implode("\n", $filtered));
            }
        }

        // Check / fix mutagen.yml
        if (!file_exists($mutagenFile)) {
            $this->addComment('DDEV Mutagen configuration missing: Create .ddev/mutagen/mutagen.yml');

            if ($dry) {
                return CheckResult::FAIL;
            }

            $mutagenDir = dirname($mutagenFile);

            if (!is_dir($mutagenDir)) {
                mkdir($mutagenDir, 0755, true);
            }

            file_put_contents($mutagenFile, Yaml::dump(['sync' => ['defaults' => ['ignore' => ['paths' => ['/node_modules']]]]], 6, 2));

            return $this->fix(dry: true);
        }

        $mutagenContent = file_get_contents($mutagenFile) ?: '';

        if (str_contains($mutagenContent, '#ddev-generated')) {
            $this->addComment('DDEV Mutagen configuration is auto-generated: Remove "#ddev-generated" comment from .ddev/mutagen/mutagen.yml to prevent DDEV from overwriting your changes');

            if ($dry) {
                return CheckResult::FAIL;
            }

            $mutagenContent = ltrim(preg_replace('/^#ddev-generated.*$/m', '', $mutagenContent) ?? $mutagenContent);
            file_put_contents($mutagenFile, $mutagenContent);
        }

        $mutagenConfig = Yaml::parseFile($mutagenFile);
        $ignorePaths = $mutagenConfig['sync']['defaults']['ignore']['paths'] ?? [];

        if (!in_array('/node_modules', $ignorePaths, true)) {
            $this->addComment('DDEV Mutagen configuration incomplete: Add "/node_modules" to sync.defaults.ignore.paths in .ddev/mutagen/mutagen.yml and run "ddev mutagen reset" to apply changes');

            if ($dry) {
                return CheckResult::FAIL;
            }

            $ignorePaths[] = '/node_modules';
            $mutagenConfig['sync']['defaults']['ignore']['paths'] = $ignorePaths;
            file_put_contents($mutagenFile, Yaml::dump($mutagenConfig, 6, 2));
        }

        return $dry ? CheckResult::PASS : $this->fix(dry: true);
    }
}
