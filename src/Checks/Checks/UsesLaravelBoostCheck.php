<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesLaravelBoostCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/boost')) {
            return CheckResult::FAIL;
        }

        if (!$this->hasPostUpdateScript('boost:update')) {
            return CheckResult::FAIL;
        }

        // Check boost.json file exists and has correct configuration
        $boostJsonFile = base_path('boost.json');

        if (!file_exists($boostJsonFile)) {
            $this->addComment('Laravel Boost configuration missing: Create boost.json in project root');

            return CheckResult::FAIL;
        }

        $boostConfig = json_decode(
            file_get_contents($boostJsonFile) ?: throw new \RuntimeException(),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $requiredAgents = ['claude_code', 'phpstorm'];
        $requiredEditors = ['claude_code', 'phpstorm', 'vscode'];

        $actualAgents = $boostConfig['agents'] ?? [];
        $actualEditors = $boostConfig['editors'] ?? [];

        // Check if all required agents are present
        $missingAgents = array_diff($requiredAgents, $actualAgents);
        if (!empty($missingAgents)) {
            $this->addComment('Laravel Boost configuration incomplete: boost.json must include agents: '.implode(', ', $requiredAgents));

            return CheckResult::FAIL;
        }

        // Check if all required editors are present
        $missingEditors = array_diff($requiredEditors, $actualEditors);
        if (!empty($missingEditors)) {
            $this->addComment('Laravel Boost configuration incomplete: boost.json must include editors: '.implode(', ', $requiredEditors));

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
