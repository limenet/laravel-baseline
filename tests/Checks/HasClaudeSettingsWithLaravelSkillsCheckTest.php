<?php

use Limenet\LaravelBaseline\Checks\Checks\HasClaudeSettingsWithLaravelSkillsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

$marketplace = ['source' => ['source' => 'github', 'repo' => 'laravel/agent-skills']];

it('hasClaudeSettingsWithLaravelSkills passes when settings file has correct configuration', function () use ($marketplace): void {
    bindFakeComposer([]);
    $settings = [
        'enabledPlugins' => [
            'laravel@laravel' => true,
        ],
        'extraKnownMarketplaces' => [
            'laravel' => $marketplace,
        ],
    ];

    $this->withTempBasePath([
        '.claude/settings.json' => json_encode($settings),
    ]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSkillsCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('hasClaudeSettingsWithLaravelSkills passes with additional plugins', function () use ($marketplace): void {
    bindFakeComposer([]);
    $settings = [
        'enabledPlugins' => [
            'laravel@laravel' => true,
            'another-plugin' => true,
        ],
        'extraKnownMarketplaces' => [
            'laravel' => $marketplace,
        ],
    ];

    $this->withTempBasePath([
        '.claude/settings.json' => json_encode($settings),
    ]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSkillsCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('hasClaudeSettingsWithLaravelSkills fails when settings file is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSkillsCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('Claude settings missing: Create .claude/settings.json with enabledPlugins configuration');
});

it('hasClaudeSettingsWithLaravelSkills fails when settings file is empty', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([
        '.claude/settings.json' => '',
    ]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSkillsCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('Claude settings empty: Add content to .claude/settings.json');
});

it('hasClaudeSettingsWithLaravelSkills fails when enabledPlugins is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.claude/settings.json' => json_encode(['someOtherSetting' => true]),
    ]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSkillsCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('Claude settings incomplete: Add "enabledPlugins" section to .claude/settings.json');
});

it('hasClaudeSettingsWithLaravelSkills fails when laravel plugin is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.claude/settings.json' => json_encode(['enabledPlugins' => ['some-other-plugin' => true]]),
    ]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSkillsCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('Claude settings incomplete: Add "laravel@laravel": true to enabledPlugins in .claude/settings.json');
});

it('hasClaudeSettingsWithLaravelSkills fails when laravel plugin is false', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.claude/settings.json' => json_encode(['enabledPlugins' => ['laravel@laravel' => false]]),
    ]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSkillsCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('Claude settings incomplete: Add "laravel@laravel": true to enabledPlugins in .claude/settings.json');
});

it('hasClaudeSettingsWithLaravelSkills fails when extraKnownMarketplaces is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.claude/settings.json' => json_encode(['enabledPlugins' => ['laravel@laravel' => true]]),
    ]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSkillsCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('Claude settings incomplete: Add laravel marketplace to extraKnownMarketplaces in .claude/settings.json');
});

it('hasClaudeSettingsWithLaravelSkills fails when extraKnownMarketplaces laravel entry is wrong', function (): void {
    bindFakeComposer([]);
    $settings = [
        'enabledPlugins' => ['laravel@laravel' => true],
        'extraKnownMarketplaces' => ['laravel' => ['source' => ['source' => 'wrong', 'repo' => 'wrong/repo']]],
    ];
    $this->withTempBasePath([
        '.claude/settings.json' => json_encode($settings),
    ]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSkillsCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('Claude settings incomplete: Add laravel marketplace to extraKnownMarketplaces in .claude/settings.json');
});
