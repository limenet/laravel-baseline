<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class HasClaudeSettingsWithLaravelSimplifierCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $settingsFile = base_path('.claude/settings.json');

        $settings = [];
        $fileExists = file_exists($settingsFile);

        if ($fileExists) {
            $content = file_get_contents($settingsFile);

            if ($content === false || trim($content) === '') {
                $this->addComment('Claude settings empty: Add content to .claude/settings.json');

                if ($dry) {
                    return CheckResult::FAIL;
                }

                // Fall through to write correct content
            } else {
                $settings = json_decode($content, true, flags: JSON_THROW_ON_ERROR) ?? [];
            }
        } else {
            $this->addComment('Claude settings missing: Create .claude/settings.json with enabledPlugins configuration');

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        $enabledPlugins = $settings['enabledPlugins'] ?? null;

        if ($dry) {
            if ($enabledPlugins === null) {
                $this->addComment('Claude settings incomplete: Add "enabledPlugins" section to .claude/settings.json');

                return CheckResult::FAIL;
            }

            if (!isset($enabledPlugins['laravel-simplifier@laravel']) || $enabledPlugins['laravel-simplifier@laravel'] !== true) {
                $this->addComment('Claude settings incomplete: Add "laravel-simplifier@laravel": true to enabledPlugins in .claude/settings.json');

                return CheckResult::FAIL;
            }

            if (!isset($enabledPlugins['laravel@laravel']) || $enabledPlugins['laravel@laravel'] !== true) {
                $this->addComment('Claude settings incomplete: Add "laravel@laravel": true to enabledPlugins in .claude/settings.json');

                return CheckResult::FAIL;
            }

            return CheckResult::PASS;
        }

        // Apply fix only if needed
        $alreadyCorrect = ($settings['enabledPlugins']['laravel-simplifier@laravel'] ?? null) === true
            && ($settings['enabledPlugins']['laravel@laravel'] ?? null) === true;

        if (!$alreadyCorrect) {
            if (!is_dir(base_path('.claude'))) {
                mkdir(base_path('.claude'), 0755, true);
            }

            $settings['enabledPlugins']['laravel-simplifier@laravel'] = true;
            $settings['enabledPlugins']['laravel@laravel'] = true;

            file_put_contents($settingsFile, json_encode($settings, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        }

        return $this->fix(dry: true);
    }
}
