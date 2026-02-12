<?php

use Limenet\LaravelBaseline\Checks\Checks\HasClaudeSettingsWithLaravelSimplifierCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('hasClaudeSettingsWithLaravelSimplifier passes when settings file has correct configuration', function (): void {
    bindFakeComposer([]);
    $settings = [
        'enabledPlugins' => [
            'laravel-simplifier@laravel' => true,
        ],
    ];

    $this->withTempBasePath([
        '.claude/settings.json' => json_encode($settings),
    ]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSimplifierCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('hasClaudeSettingsWithLaravelSimplifier fails when settings file is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSimplifierCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('Claude settings missing: Create .claude/settings.json with enabledPlugins configuration');
});

it('hasClaudeSettingsWithLaravelSimplifier fails when enabledPlugins is missing', function (): void {
    bindFakeComposer([]);
    $settings = [
        'someOtherSetting' => true,
    ];

    $this->withTempBasePath([
        '.claude/settings.json' => json_encode($settings),
    ]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSimplifierCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('Claude settings incomplete: Add "enabledPlugins" section to .claude/settings.json');
});

it('hasClaudeSettingsWithLaravelSimplifier fails when laravel-simplifier plugin is missing', function (): void {
    bindFakeComposer([]);
    $settings = [
        'enabledPlugins' => [
            'some-other-plugin' => true,
        ],
    ];

    $this->withTempBasePath([
        '.claude/settings.json' => json_encode($settings),
    ]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSimplifierCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('Claude settings incomplete: Add "laravel-simplifier@laravel": true to enabledPlugins in .claude/settings.json');
});

it('hasClaudeSettingsWithLaravelSimplifier fails when laravel-simplifier plugin is false', function (): void {
    bindFakeComposer([]);
    $settings = [
        'enabledPlugins' => [
            'laravel-simplifier@laravel' => false,
        ],
    ];

    $this->withTempBasePath([
        '.claude/settings.json' => json_encode($settings),
    ]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSimplifierCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('Claude settings incomplete: Add "laravel-simplifier@laravel": true to enabledPlugins in .claude/settings.json');
});

it('hasClaudeSettingsWithLaravelSimplifier passes with additional plugins', function (): void {
    bindFakeComposer([]);
    $settings = [
        'enabledPlugins' => [
            'laravel-simplifier@laravel' => true,
            'another-plugin' => true,
        ],
    ];

    $this->withTempBasePath([
        '.claude/settings.json' => json_encode($settings),
    ]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSimplifierCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('hasClaudeSettingsWithLaravelSimplifier fails when settings file is empty', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([
        '.claude/settings.json' => '',
    ]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSimplifierCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('Claude settings empty: Add content to .claude/settings.json');
});

it('hasClaudeSettingsWithLaravelSimplifier fails when enabledPlugins is empty', function (): void {
    bindFakeComposer([]);
    $settings = [
        'enabledPlugins' => [],
    ];

    $this->withTempBasePath([
        '.claude/settings.json' => json_encode($settings),
    ]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSimplifierCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('Claude settings incomplete: Add "laravel-simplifier@laravel": true to enabledPlugins in .claude/settings.json');
});
