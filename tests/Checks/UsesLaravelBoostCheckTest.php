<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesLaravelBoostCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesLaravelBoost fails when not installed and passes when installed', function (): void {
    bindFakeComposer(['laravel/boost' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    bindFakeComposer(['laravel/boost' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan boost:update']]];
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
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan other:command']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesLaravelBoost fails when boost.json is missing', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan boost:update']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(UsesLaravelBoostCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    $comments = $check->getComments();
    expect($comments)->toContain('Laravel Boost configuration missing: Create boost.json in project root');
});

it('usesLaravelBoost fails when boost.json has missing agents', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan boost:update']]];
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

it('usesLaravelBoost fails when boost.json has missing editors', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan boost:update']]];
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

it('usesLaravelBoost fails when boost.json has empty agents array', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan boost:update']]];
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

it('usesLaravelBoost fails when boost.json has empty editors array', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan boost:update']]];
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

it('usesLaravelBoost passes when boost.json has extra agents and editors', function (): void {
    bindFakeComposer(['laravel/boost' => true]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan boost:update']]];
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
