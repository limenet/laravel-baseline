<?php

use Limenet\LaravelBaseline\Checks\Checks\HasClaudeSettingsWithLaravelSimplifierCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('hasClaudeSettingsWithLaravelSimplifier implements FixableInterface', function (): void {
    expect(makeCheck(HasClaudeSettingsWithLaravelSimplifierCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('hasClaudeSettingsWithLaravelSimplifier fix creates settings file when missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSimplifierCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect(file_exists(base_path('.claude/settings.json')))->toBeTrue();
});

it('hasClaudeSettingsWithLaravelSimplifier fix merges into existing settings', function (): void {
    bindFakeComposer([]);
    $existing = ['someOtherSetting' => true, 'enabledPlugins' => ['other-plugin' => true]];
    $this->withTempBasePath(['.claude/settings.json' => json_encode($existing)]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSimplifierCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true);
    expect($settings['someOtherSetting'])->toBeTrue();
    expect($settings['enabledPlugins']['other-plugin'])->toBeTrue();
    expect($settings['enabledPlugins']['laravel-simplifier@laravel'])->toBeTrue();
    expect($settings['enabledPlugins']['laravel@laravel'])->toBeTrue();
});

it('hasClaudeSettingsWithLaravelSimplifier fix is idempotent', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    $check = makeCheck(HasClaudeSettingsWithLaravelSimplifierCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect($check->fix())->toBe(CheckResult::PASS);
});
