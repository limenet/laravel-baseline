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

        $requiredAgents = ['claude_code', 'copilot', 'junie'];
        $missingAgents = array_diff($requiredAgents, $boostConfig['agents'] ?? []);
        if (!empty($missingAgents)) {
            $this->addComment('Laravel Boost v2 configuration incomplete: boost.json must include agents: '.implode(', ', $requiredAgents));

            return CheckResult::FAIL;
        }

        if (($boostConfig['guidelines'] ?? null) !== true) {
            $this->addComment('Laravel Boost v2 configuration incomplete: boost.json must set "guidelines": true');

            return CheckResult::FAIL;
        }

        if (($boostConfig['mcp'] ?? null) !== true) {
            $this->addComment('Laravel Boost v2 configuration incomplete: boost.json must set "mcp": true');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }
}
