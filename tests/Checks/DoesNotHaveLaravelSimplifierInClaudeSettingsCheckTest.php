<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotHaveLaravelSimplifierInClaudeSettingsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('doesNotHaveLaravelSimplifierInClaudeSettings passes when settings file is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    expect(makeCheck(DoesNotHaveLaravelSimplifierInClaudeSettingsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotHaveLaravelSimplifierInClaudeSettings passes when settings file is empty', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['.claude/settings.json' => '']);

    expect(makeCheck(DoesNotHaveLaravelSimplifierInClaudeSettingsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotHaveLaravelSimplifierInClaudeSettings passes when laravel-simplifier is not present', function (): void {
    bindFakeComposer([]);
    $settings = ['enabledPlugins' => ['laravel@laravel' => true]];
    $this->withTempBasePath(['.claude/settings.json' => json_encode($settings)]);

    expect(makeCheck(DoesNotHaveLaravelSimplifierInClaudeSettingsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotHaveLaravelSimplifierInClaudeSettings passes when laravel-simplifier is explicitly false', function (): void {
    bindFakeComposer([]);
    $settings = ['enabledPlugins' => ['laravel-simplifier@laravel' => false]];
    $this->withTempBasePath(['.claude/settings.json' => json_encode($settings)]);

    expect(makeCheck(DoesNotHaveLaravelSimplifierInClaudeSettingsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotHaveLaravelSimplifierInClaudeSettings fails when laravel-simplifier is present', function (): void {
    bindFakeComposer([]);
    $settings = ['enabledPlugins' => ['laravel-simplifier@laravel' => true]];
    $this->withTempBasePath(['.claude/settings.json' => json_encode($settings)]);

    [$check, $collector] = makeCheckWithCollector(DoesNotHaveLaravelSimplifierInClaudeSettingsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Remove "laravel-simplifier@laravel" from enabledPlugins in .claude/settings.json — the plugin no longer exists');
});
