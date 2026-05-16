<?php

use Limenet\LaravelBaseline\Checks\Checks\HasClaudeSettingsWithLaravelSkillsCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('hasClaudeSettingsWithLaravelSkills implements FixableInterface', function (): void {
    expect(makeCheck(HasClaudeSettingsWithLaravelSkillsCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('hasClaudeSettingsWithLaravelSkills fix creates settings file when missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSkillsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect(file_exists(base_path('.claude/settings.json')))->toBeTrue();
});

it('hasClaudeSettingsWithLaravelSkills fix merges into existing settings', function (): void {
    bindFakeComposer([]);
    $existing = ['someOtherSetting' => true, 'enabledPlugins' => ['other-plugin' => true]];
    $this->withTempBasePath(['.claude/settings.json' => json_encode($existing)]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSkillsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true);
    expect($settings['someOtherSetting'])->toBeTrue();
    expect($settings['enabledPlugins']['other-plugin'])->toBeTrue();
    expect($settings['enabledPlugins']['laravel@laravel'])->toBeTrue();
    expect($settings['extraKnownMarketplaces']['laravel'])->toBe([
        'source' => ['source' => 'github', 'repo' => 'laravel/agent-skills'],
    ]);
});

it('hasClaudeSettingsWithLaravelSkills fix is idempotent', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSkillsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect($check->fix())->toBe(CheckResult::PASS);
});
