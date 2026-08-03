<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotHaveCopilotOrJunieAgentFilesCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('doesNotHaveCopilotOrJunieAgentFiles passes when neither AGENTS.md nor .junie exist', function (): void {
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(DoesNotHaveCopilotOrJunieAgentFilesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotHaveCopilotOrJunieAgentFiles fails when AGENTS.md exists', function (): void {
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'AGENTS.md' => '# Agents',
    ]);

    [$check, $collector] = makeCheckWithCollector(DoesNotHaveCopilotOrJunieAgentFilesCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Remove AGENTS.md — it is generated for the Copilot/Junie Boost agents, which are no longer required');
});

it('doesNotHaveCopilotOrJunieAgentFiles fails when .junie directory exists', function (): void {
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.junie/mcp/mcp.json' => json_encode(['mcpServers' => []]),
    ]);

    [$check, $collector] = makeCheckWithCollector(DoesNotHaveCopilotOrJunieAgentFilesCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Remove the .junie directory — it is generated for the Junie Boost agent, which is no longer required');
});

it('doesNotHaveCopilotOrJunieAgentFiles fix deletes AGENTS.md and the .junie directory', function (): void {
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'AGENTS.md' => '# Agents',
        '.junie/mcp/mcp.json' => json_encode(['mcpServers' => []]),
    ]);

    $check = makeCheck(DoesNotHaveCopilotOrJunieAgentFilesCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    expect(file_exists(base_path('AGENTS.md')))->toBeFalse();
    expect(is_dir(base_path('.junie')))->toBeFalse();
});
