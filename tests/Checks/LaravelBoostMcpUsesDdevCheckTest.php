<?php

use Limenet\LaravelBaseline\Checks\Checks\LaravelBoostMcpUsesDdevCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

$validMcp = [
    'mcpServers' => [
        'laravel-boost' => [
            'command' => 'ddev',
            'args' => ['artisan', 'boost:mcp', '--no-ansi', '-q'],
        ],
    ],
];

it('laravelBoostMcpUsesDdev warns when laravel/boost is not installed', function (): void {
    bindFakeComposer(['laravel/boost' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(LaravelBoostMcpUsesDdevCheck::class)->check())->toBe(CheckResult::WARN);
});

it('laravelBoostMcpUsesDdev fails when .mcp.json is missing', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(LaravelBoostMcpUsesDdevCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('MCP configuration incorrect: .mcp.json mcpServers.laravel-boost must use ddev');
});

it('laravelBoostMcpUsesDdev fails when laravel-boost server key is absent', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.mcp.json' => json_encode(['mcpServers' => []]),
    ]);

    [$check, $collector] = makeCheckWithCollector(LaravelBoostMcpUsesDdevCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('MCP configuration incorrect: .mcp.json mcpServers.laravel-boost must use ddev');
});

it('laravelBoostMcpUsesDdev fails when command is not ddev', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $mcp = ['mcpServers' => ['laravel-boost' => ['command' => 'php', 'args' => ['artisan', 'boost:mcp', '--no-ansi', '-q']]]];
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.mcp.json' => json_encode($mcp),
    ]);

    expect(makeCheck(LaravelBoostMcpUsesDdevCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('laravelBoostMcpUsesDdev fails when args are wrong', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $mcp = ['mcpServers' => ['laravel-boost' => ['command' => 'ddev', 'args' => ['artisan', 'boost:mcp']]]];
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.mcp.json' => json_encode($mcp),
    ]);

    expect(makeCheck(LaravelBoostMcpUsesDdevCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('laravelBoostMcpUsesDdev passes with correct configuration', function () use ($validMcp): void {
    bindFakeComposer(['laravel/boost' => true]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.mcp.json' => json_encode($validMcp),
    ]);

    expect(makeCheck(LaravelBoostMcpUsesDdevCheck::class)->check())->toBe(CheckResult::PASS);
});

it('laravelBoostMcpUsesDdev passes with extra servers alongside laravel-boost', function () use ($validMcp): void {
    bindFakeComposer(['laravel/boost' => true]);
    $mcp = $validMcp;
    $mcp['mcpServers']['other-server'] = ['command' => 'npx', 'args' => ['some-server']];
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.mcp.json' => json_encode($mcp),
    ]);

    expect(makeCheck(LaravelBoostMcpUsesDdevCheck::class)->check())->toBe(CheckResult::PASS);
});

it('laravelBoostMcpUsesDdev fix creates .mcp.json when missing', function () use ($validMcp): void {
    bindFakeComposer(['laravel/boost' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(LaravelBoostMcpUsesDdevCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $written = json_decode(file_get_contents(base_path('.mcp.json')), true);
    expect($written['mcpServers']['laravel-boost'])->toBe($validMcp['mcpServers']['laravel-boost']);
});

it('laravelBoostMcpUsesDdev fix overwrites wrong config and preserves other servers', function () use ($validMcp): void {
    bindFakeComposer(['laravel/boost' => true]);
    $mcp = [
        'mcpServers' => [
            'laravel-boost' => ['command' => 'php', 'args' => ['artisan', 'boost:mcp']],
            'other-server' => ['command' => 'npx', 'args' => ['some-server']],
        ],
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        '.mcp.json' => json_encode($mcp),
    ]);

    $check = makeCheck(LaravelBoostMcpUsesDdevCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $written = json_decode(file_get_contents(base_path('.mcp.json')), true);
    expect($written['mcpServers']['laravel-boost'])->toBe($validMcp['mcpServers']['laravel-boost']);
    expect($written['mcpServers']['other-server'])->toBe(['command' => 'npx', 'args' => ['some-server']]);
});
