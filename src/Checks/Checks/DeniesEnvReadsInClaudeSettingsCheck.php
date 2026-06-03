<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractClaudeSettingsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Symfony\Component\Finder\Finder;

class DeniesEnvReadsInClaudeSettingsCheck extends AbstractClaudeSettingsCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $required = $this->requiredDenyEntries();

        $settings = $this->readClaudeSettings() ?? [];

        /** @var list<string> $deny */
        $deny = $settings['permissions']['deny'] ?? [];

        $missing = array_values(array_diff($required, $deny));

        if ($missing === []) {
            return CheckResult::PASS;
        }

        foreach ($missing as $entry) {
            $this->addComment("Claude settings: add \"{$entry}\" to permissions.deny in .claude/settings.json — prevents Claude from reading secrets");
        }

        if ($dry) {
            return CheckResult::FAIL;
        }

        $settings['permissions']['deny'] = $this->mergeMissing($deny, $required);

        $this->writeClaudeSettings($settings);

        return CheckResult::PASS;
    }

    /**
     * Deny the base `.env` plus every environment that ships an encrypted file
     * (`.env.{environment}.encrypted` → deny `.env.{environment}`).
     *
     * `.env.example` is intentionally never denied so it stays readable.
     *
     * @return list<string>
     */
    private function requiredDenyEntries(): array
    {
        $entries = ['Read(./.env)'];

        $finder = (new Finder())
            ->in(base_path())
            ->ignoreDotFiles(false)
            ->name('.env.*.encrypted')
            ->depth('== 0');

        foreach ($finder as $file) {
            // .env.staging.encrypted → .env.staging
            $plaintext = substr($file->getFilename(), 0, -strlen('.encrypted'));
            $entries[] = "Read(./{$plaintext})";
        }

        return $entries;
    }
}
