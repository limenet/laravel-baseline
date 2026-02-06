<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesLaravelBoostCheck;
use Limenet\LaravelBaseline\Checks\CommentCollector;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Mockery\MockInterface;

// === V1 Tests ===

it('usesLaravelBoost fails when not installed and passes when installed', function (): void {
    bindFakeComposer(['laravel/boost' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => '^1.0'],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $boostConfig = [
        'agents' => ['claude_code', 'phpstorm'],
        'editors' => ['claude_code', 'phpstorm', 'vscode'],
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('usesLaravelBoost fails when boost:update post-update script is missing', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => '^1.0'],
        'scripts' => ['post-update-cmd' => ['php artisan other:command']],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesLaravelBoost fails when boost.json is missing', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => '^1.0'],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    $comments = $check->getComments();
    expect($comments)->toContain('Laravel Boost configuration missing: Create boost.json in project root');
});

it('usesLaravelBoost v1 fails when boost.json has missing agents', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => '^1.0'],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $boostConfig = [
        'agents' => ['claude_code'],  // missing phpstorm
        'editors' => ['claude_code', 'phpstorm', 'vscode'],
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    $comments = $check->getComments();
    expect($comments)->toContain('Laravel Boost configuration incomplete: boost.json must include agents: claude_code, phpstorm');
});

it('usesLaravelBoost v1 fails when boost.json has missing editors', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => '^1.0'],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $boostConfig = [
        'agents' => ['claude_code', 'phpstorm'],
        'editors' => ['claude_code', 'phpstorm'],  // missing vscode
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    $comments = $check->getComments();
    expect($comments)->toContain('Laravel Boost configuration incomplete: boost.json must include editors: claude_code, phpstorm, vscode');
});

it('usesLaravelBoost v1 fails when boost.json has empty agents array', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => '^1.0'],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $boostConfig = [
        'agents' => [],
        'editors' => ['claude_code', 'phpstorm', 'vscode'],
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesLaravelBoost v1 fails when boost.json has empty editors array', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => '^1.0'],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $boostConfig = [
        'agents' => ['claude_code', 'phpstorm'],
        'editors' => [],
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesLaravelBoost v1 passes when boost.json has extra agents and editors', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => '^1.0'],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $boostConfig = [
        'agents' => ['claude_code', 'phpstorm', 'cursor'],  // extra agent
        'editors' => ['claude_code', 'phpstorm', 'vscode', 'sublime'],  // extra editor
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

// === V2 Tests ===

it('usesLaravelBoost v2 passes with correct configuration', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => '^2.0'],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $boostConfig = [
        'agents' => ['claude_code', 'copilot', 'junie'],
        'guidelines' => true,
        'mcp' => true,
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('usesLaravelBoost v2 passes with extra keys and agents', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => '^2.0'],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $boostConfig = [
        'agents' => ['claude_code', 'copilot', 'junie', 'cursor'],
        'guidelines' => true,
        'mcp' => true,
        'extra_key' => 'extra_value',
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('usesLaravelBoost v2 fails when agents are missing', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => '^2.0'],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $boostConfig = [
        'agents' => ['claude_code'],  // missing copilot and junie
        'guidelines' => true,
        'mcp' => true,
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    $comments = $check->getComments();
    expect($comments)->toContain('Laravel Boost v2 configuration incomplete: boost.json must include agents: claude_code, copilot, junie');
});

it('usesLaravelBoost v2 fails when guidelines is not true', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => '^2.0'],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $boostConfig = [
        'agents' => ['claude_code', 'copilot', 'junie'],
        'guidelines' => false,
        'mcp' => true,
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    $comments = $check->getComments();
    expect($comments)->toContain('Laravel Boost v2 configuration incomplete: boost.json must set "guidelines": true');
});

it('usesLaravelBoost v2 fails when guidelines is missing', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => '^2.0'],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $boostConfig = [
        'agents' => ['claude_code', 'copilot', 'junie'],
        'mcp' => true,
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    $comments = $check->getComments();
    expect($comments)->toContain('Laravel Boost v2 configuration incomplete: boost.json must set "guidelines": true');
});

it('usesLaravelBoost v2 fails when mcp is not true', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => '^2.0'],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $boostConfig = [
        'agents' => ['claude_code', 'copilot', 'junie'],
        'guidelines' => true,
        'mcp' => false,
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    $comments = $check->getComments();
    expect($comments)->toContain('Laravel Boost v2 configuration incomplete: boost.json must set "mcp": true');
});

it('usesLaravelBoost v2 fails when mcp is missing', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => '^2.0'],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $boostConfig = [
        'agents' => ['claude_code', 'copilot', 'junie'],
        'guidelines' => true,
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    $comments = $check->getComments();
    expect($comments)->toContain('Laravel Boost v2 configuration incomplete: boost.json must set "mcp": true');
});

it('usesLaravelBoost v2 detected from require-dev', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require-dev' => ['laravel/boost' => '^2.0'],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $boostConfig = [
        'agents' => ['claude_code', 'copilot', 'junie'],
        'guidelines' => true,
        'mcp' => true,
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('usesLaravelBoost v2 detected with various version constraints', function (string $constraint): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = [
        'require' => ['laravel/boost' => $constraint],
        'scripts' => ['post-update-cmd' => ['php artisan boost:update']],
    ];
    $boostConfig = [
        'agents' => ['claude_code', 'copilot', 'junie'],
        'guidelines' => true,
        'mcp' => true,
    ];
    $this->withTempBasePath([
        'composer.json' => json_encode($composer),
        'boost.json' => json_encode($boostConfig),
    ]);

    /** @var UsesLaravelBoostCheck|MockInterface $check */
    $check = Mockery::mock(UsesLaravelBoostCheck::class, [new CommentCollector()])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();

    $check->shouldReceive('checkV2')->once()->andReturn(CheckResult::PASS);
    $check->shouldNotReceive('checkV1');

    expect($check->check())->toBe(CheckResult::PASS);
})->with([
    '^2.0',
    '^2.1',
    '^2.1.1',
    '~2.3',
    '>=2.0',
    '2.*',
]);
