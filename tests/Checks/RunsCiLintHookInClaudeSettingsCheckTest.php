<?php

use Limenet\LaravelBaseline\Checks\Checks\RunsCiLintHookInClaudeSettingsCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

$expectedGroup = [
    'matcher' => '',
    'hooks' => [['type' => 'command', 'command' => 'ddev composer run ci-lint']],
];

it('runsCiLintHookInClaudeSettings implements FixableInterface', function (): void {
    expect(makeCheck(RunsCiLintHookInClaudeSettingsCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('runsCiLintHookInClaudeSettings passes when Stop hook is configured', function () use ($expectedGroup): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.claude/settings.json' => json_encode(['hooks' => ['Stop' => [$expectedGroup]]]),
    ]);

    expect(makeCheck(RunsCiLintHookInClaudeSettingsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('runsCiLintHookInClaudeSettings passes when hook lives alongside other Stop hooks', function () use ($expectedGroup): void {
    bindFakeComposer([]);
    $other = ['matcher' => '', 'hooks' => [['type' => 'command', 'command' => 'echo done']]];
    $this->withTempBasePath([
        '.claude/settings.json' => json_encode(['hooks' => ['Stop' => [$other, $expectedGroup]]]),
    ]);

    expect(makeCheck(RunsCiLintHookInClaudeSettingsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('runsCiLintHookInClaudeSettings fails when settings file is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    [$check, $collector] = makeCheckWithCollector(RunsCiLintHookInClaudeSettingsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Claude settings: add a Stop hook running "ddev composer run ci-lint" to .claude/settings.json');
});

it('runsCiLintHookInClaudeSettings fails when settings file is empty', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['.claude/settings.json' => '']);

    expect(makeCheck(RunsCiLintHookInClaudeSettingsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('runsCiLintHookInClaudeSettings fails when Stop hooks exist without ci-lint', function (): void {
    bindFakeComposer([]);
    $other = ['matcher' => '', 'hooks' => [['type' => 'command', 'command' => 'echo done']]];
    $this->withTempBasePath([
        '.claude/settings.json' => json_encode(['hooks' => ['Stop' => [$other]]]),
    ]);

    expect(makeCheck(RunsCiLintHookInClaudeSettingsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('runsCiLintHookInClaudeSettings fix creates settings file when missing', function () use ($expectedGroup): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    expect(makeCheck(RunsCiLintHookInClaudeSettingsCheck::class)->fix())->toBe(CheckResult::PASS);

    $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true);
    expect($settings['hooks']['Stop'])->toBe([$expectedGroup]);
});

it('runsCiLintHookInClaudeSettings fix appends without clobbering existing Stop hooks', function (): void {
    bindFakeComposer([]);
    $other = ['matcher' => '', 'hooks' => [['type' => 'command', 'command' => 'echo done']]];
    $this->withTempBasePath([
        '.claude/settings.json' => json_encode(['hooks' => ['Stop' => [$other]]]),
    ]);

    expect(makeCheck(RunsCiLintHookInClaudeSettingsCheck::class)->fix())->toBe(CheckResult::PASS);

    $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true);
    expect($settings['hooks']['Stop'])->toHaveCount(2);
    expect($settings['hooks']['Stop'][0])->toBe($other);
    expect($settings['hooks']['Stop'][1]['hooks'][0]['command'])->toBe('ddev composer run ci-lint');
});

it('runsCiLintHookInClaudeSettings fix is idempotent', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    $check = makeCheck(RunsCiLintHookInClaudeSettingsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect($check->fix())->toBe(CheckResult::PASS);
});
