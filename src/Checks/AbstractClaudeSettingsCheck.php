<?php

namespace Limenet\LaravelBaseline\Checks;

abstract class AbstractClaudeSettingsCheck extends AbstractFixableCheck
{
    protected function claudeSettingsFile(): string
    {
        return base_path('.claude/settings.json');
    }

    /**
     * Read and decode .claude/settings.json. Returns null when the file is
     * missing or empty (callers decide whether that is a failure).
     *
     * @return array<string,mixed>|null
     */
    protected function readClaudeSettings(): ?array
    {
        $file = $this->claudeSettingsFile();

        if (!file_exists($file)) {
            return null;
        }

        $content = file_get_contents($file);

        if ($content === false || trim($content) === '') {
            return null;
        }

        return json_decode($content, true, flags: JSON_THROW_ON_ERROR) ?? [];
    }

    /**
     * Distinguish the three states callers may need to message differently:
     * 'missing' (no file), 'empty' (file present but blank), or 'present'.
     */
    protected function claudeSettingsState(): string
    {
        $file = $this->claudeSettingsFile();

        if (!file_exists($file)) {
            return 'missing';
        }

        $content = file_get_contents($file);

        if ($content === false || trim($content) === '') {
            return 'empty';
        }

        return 'present';
    }

    /**
     * @param  array<string,mixed>  $settings
     */
    protected function writeClaudeSettings(array $settings): void
    {
        if (!is_dir(base_path('.claude'))) {
            mkdir(base_path('.claude'), 0755, true);
        }

        file_put_contents(
            $this->claudeSettingsFile(),
            json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n",
        );
    }

    /**
     * Append any required values that are not already present, preserving
     * existing entries and order.
     *
     * @param  list<string>  $existing
     * @param  list<string>  $required
     * @return list<string>
     */
    protected function mergeMissing(array $existing, array $required): array
    {
        foreach ($required as $value) {
            if (!in_array($value, $existing, true)) {
                $existing[] = $value;
            }
        }

        return array_values($existing);
    }
}
