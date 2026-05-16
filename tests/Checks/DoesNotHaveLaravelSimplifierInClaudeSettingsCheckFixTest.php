<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotHaveLaravelSimplifierInClaudeSettingsCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('doesNotHaveLaravelSimplifierInClaudeSettings implements FixableInterface', function (): void {
    expect(makeCheck(DoesNotHaveLaravelSimplifierInClaudeSettingsCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('doesNotHaveLaravelSimplifierInClaudeSettings fix removes laravel-simplifier entry', function (): void {
    bindFakeComposer([]);
    $settings = [
        'enabledPlugins' => [
            'laravel-simplifier@laravel' => true,
            'laravel@laravel' => true,
        ],
    ];
    $this->withTempBasePath(['.claude/settings.json' => json_encode($settings)]);

    $check = makeCheck(DoesNotHaveLaravelSimplifierInClaudeSettingsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $written = json_decode(file_get_contents(base_path('.claude/settings.json')), true);
    expect($written['enabledPlugins'])->not->toHaveKey('laravel-simplifier@laravel');
    expect($written['enabledPlugins']['laravel@laravel'])->toBeTrue();
});

it('doesNotHaveLaravelSimplifierInClaudeSettings fix is idempotent', function (): void {
    bindFakeComposer([]);
    $settings = ['enabledPlugins' => ['laravel-simplifier@laravel' => true]];
    $this->withTempBasePath(['.claude/settings.json' => json_encode($settings)]);

    $check = makeCheck(DoesNotHaveLaravelSimplifierInClaudeSettingsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect($check->fix())->toBe(CheckResult::PASS);
});
