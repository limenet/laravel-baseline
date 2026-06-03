<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractClaudeSettingsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class HasClaudeSettingsWithLaravelSkillsCheck extends AbstractClaudeSettingsCheck
{
    private const MARKETPLACE = ['source' => ['source' => 'github', 'repo' => 'laravel/agent-skills']];

    public function fix(bool $dry = false): CheckResult
    {
        $state = $this->claudeSettingsState();

        if ($state === 'empty') {
            $this->addComment('Claude settings empty: Add content to .claude/settings.json');

            if ($dry) {
                return CheckResult::FAIL;
            }
        } elseif ($state === 'missing') {
            $this->addComment('Claude settings missing: Create .claude/settings.json with enabledPlugins configuration');

            if ($dry) {
                return CheckResult::FAIL;
            }
        }

        $settings = $this->readClaudeSettings() ?? [];

        $enabledPlugins = $settings['enabledPlugins'] ?? null;
        $extraKnownMarketplaces = $settings['extraKnownMarketplaces'] ?? null;

        if ($dry) {
            if ($enabledPlugins === null) {
                $this->addComment('Claude settings incomplete: Add "enabledPlugins" section to .claude/settings.json');

                return CheckResult::FAIL;
            }

            if (!isset($enabledPlugins['laravel@laravel']) || $enabledPlugins['laravel@laravel'] !== true) {
                $this->addComment('Claude settings incomplete: Add "laravel@laravel": true to enabledPlugins in .claude/settings.json');

                return CheckResult::FAIL;
            }

            if (($extraKnownMarketplaces['laravel'] ?? null) !== self::MARKETPLACE) {
                $this->addComment('Claude settings incomplete: Add laravel marketplace to extraKnownMarketplaces in .claude/settings.json');

                return CheckResult::FAIL;
            }

            return CheckResult::PASS;
        }

        $alreadyCorrect = ($settings['enabledPlugins']['laravel@laravel'] ?? null) === true
            && ($settings['extraKnownMarketplaces']['laravel'] ?? null) === self::MARKETPLACE;

        if (!$alreadyCorrect) {
            $settings['enabledPlugins']['laravel@laravel'] = true;
            $settings['extraKnownMarketplaces']['laravel'] = self::MARKETPLACE;

            $this->writeClaudeSettings($settings);
        }

        return $this->fix(dry: true);
    }
}
