<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractClaudeSettingsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class RunsCiLintHookInClaudeSettingsCheck extends AbstractClaudeSettingsCheck
{
    private const string COMMAND = 'ddev composer run ci-lint';

    private const array EXPECTED_GROUP = [
        'matcher' => '',
        'hooks' => [
            ['type' => 'command', 'command' => self::COMMAND],
        ],
    ];

    public function fix(bool $dry = false): CheckResult
    {
        $settings = $this->readClaudeSettings() ?? [];

        /** @var list<array<string,mixed>> $stopGroups */
        $stopGroups = $settings['hooks']['Stop'] ?? [];

        if ($this->hasCiLintHook($stopGroups)) {
            return CheckResult::PASS;
        }

        $this->addComment('Claude settings: add a Stop hook running "'.self::COMMAND.'" to .claude/settings.json');

        if ($dry) {
            return CheckResult::FAIL;
        }

        $stopGroups[] = self::EXPECTED_GROUP;
        $settings['hooks']['Stop'] = array_values($stopGroups);

        $this->writeClaudeSettings($settings);

        return CheckResult::PASS;
    }

    /**
     * @param  list<array<string,mixed>>  $stopGroups
     */
    private function hasCiLintHook(array $stopGroups): bool
    {
        foreach ($stopGroups as $group) {
            foreach ($group['hooks'] ?? [] as $hook) {
                if (($hook['command'] ?? null) === self::COMMAND) {
                    return true;
                }
            }
        }

        return false;
    }
}
