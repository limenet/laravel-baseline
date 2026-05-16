<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class LaravelBoostMcpUsesDdevCheck extends AbstractFixableCheck
{
    private const array EXPECTED_SERVER = [
        'command' => 'ddev',
        'args' => ['artisan', 'boost:mcp', '--no-ansi', '-q'],
    ];

    public function fix(bool $dry = false): CheckResult
    {
        if (!$this->checkComposerPackages('laravel/boost')) {
            return CheckResult::WARN;
        }

        $mcpFile = base_path('.mcp.json');

        $mcp = file_exists($mcpFile)
            ? json_decode(file_get_contents($mcpFile) ?: throw new \RuntimeException(), true, flags: JSON_THROW_ON_ERROR)
            : [];

        $server = $mcp['mcpServers']['laravel-boost'] ?? null;

        if ($server !== self::EXPECTED_SERVER) {
            $this->addComment('MCP configuration incorrect: .mcp.json mcpServers.laravel-boost must use ddev');

            if ($dry) {
                return CheckResult::FAIL;
            }

            $mcp['mcpServers']['laravel-boost'] = self::EXPECTED_SERVER;
            file_put_contents($mcpFile, json_encode($mcp, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
        }

        return CheckResult::PASS;
    }
}
