<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractClaudeSettingsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

class RunsCiLintHookInClaudeSettingsCheck extends AbstractClaudeSettingsCheck
{
    public static function profiles(): array
    {
        return Profile::cases();
    }

    public function fix(bool $dry = false): CheckResult
    {
        $command = $this->policy()->string('claude.ciLintHookCommand.composer');

        $settings = $this->readClaudeSettings() ?? [];

        /** @var list<array<string,mixed>> $stopGroups */
        $stopGroups = $settings['hooks']['Stop'] ?? [];

        if ($this->hasCiLintHook($stopGroups, $command)) {
            return CheckResult::PASS;
        }

        $this->addComment('Claude settings: add a Stop hook running "'.$command.'" to .claude/settings.json');

        if ($dry) {
            return CheckResult::FAIL;
        }

        $stopGroups[] = [
            'matcher' => '',
            'hooks' => [
                ['type' => 'command', 'command' => $command],
            ],
        ];
        $settings['hooks']['Stop'] = array_values($stopGroups);

        $this->writeClaudeSettings($settings);

        return CheckResult::PASS;
    }

    /**
     * @param  list<array<string,mixed>>  $stopGroups
     */
    private function hasCiLintHook(array $stopGroups, string $command): bool
    {
        foreach ($stopGroups as $group) {
            foreach ($group['hooks'] ?? [] as $hook) {
                if (($hook['command'] ?? null) === $command) {
                    return true;
                }
            }
        }

        return false;
    }
}
