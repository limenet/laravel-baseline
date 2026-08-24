<?php

use Limenet\LaravelBaseline\Checks\Checks\AsksBeforeDestructiveDbCommandsInClaudeSettingsCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Policy\Policy;

/**
 * @return list<string>
 */
function requiredAskEntries(): array
{
    return Policy::fromDirectory()->strings('claude.ask.php');
}

it('asksBeforeDestructiveDbCommandsInClaudeSettings implements FixableInterface', function (): void {
    expect(makeCheck(AsksBeforeDestructiveDbCommandsInClaudeSettingsCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('asksBeforeDestructiveDbCommandsInClaudeSettings passes when every destructive command is gated', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.claude/settings.json' => json_encode(['permissions' => ['ask' => requiredAskEntries()]]),
    ]);

    expect(makeCheck(AsksBeforeDestructiveDbCommandsInClaudeSettingsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('asksBeforeDestructiveDbCommandsInClaudeSettings fails when settings file is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    [$check, $collector] = makeCheckWithCollector(AsksBeforeDestructiveDbCommandsInClaudeSettingsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Claude settings: add "Bash(ddev artisan migrate:fresh:*)" to permissions.ask in .claude/settings.json — destroys database contents, so it must be confirmed every time');
});

it('asksBeforeDestructiveDbCommandsInClaudeSettings fails when settings file is empty', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['.claude/settings.json' => '']);

    expect(makeCheck(AsksBeforeDestructiveDbCommandsInClaudeSettingsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('asksBeforeDestructiveDbCommandsInClaudeSettings fails when only some commands are gated', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.claude/settings.json' => json_encode(['permissions' => ['ask' => ['Bash(ddev artisan migrate:fresh:*)']]]),
    ]);

    [$check, $collector] = makeCheckWithCollector(AsksBeforeDestructiveDbCommandsInClaudeSettingsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Claude settings: add "Bash(ddev artisan db:wipe:*)" to permissions.ask in .claude/settings.json — destroys database contents, so it must be confirmed every time');
    expect($collector->all())->not->toContain('Claude settings: add "Bash(ddev artisan migrate:fresh:*)" to permissions.ask in .claude/settings.json — destroys database contents, so it must be confirmed every time');
});

it('asksBeforeDestructiveDbCommandsInClaudeSettings gates both the ddev and bare php invocation forms', function (): void {
    expect(requiredAskEntries())->toContain('Bash(ddev artisan migrate:fresh:*)');
    expect(requiredAskEntries())->toContain('Bash(php artisan migrate:fresh:*)');
});

it('asksBeforeDestructiveDbCommandsInClaudeSettings does not gate a plain forward migrate', function (): void {
    expect(requiredAskEntries())->not->toContain('Bash(ddev artisan migrate:*)');
    expect(requiredAskEntries())->not->toContain('Bash(php artisan migrate:*)');
});

it('asksBeforeDestructiveDbCommandsInClaudeSettings fix creates settings gating every destructive command', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    expect(makeCheck(AsksBeforeDestructiveDbCommandsInClaudeSettingsCheck::class)->fix())->toBe(CheckResult::PASS);

    $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true);
    expect($settings['permissions']['ask'])->toBe(requiredAskEntries());
});

it('asksBeforeDestructiveDbCommandsInClaudeSettings fix merges into existing settings preserving other entries', function (): void {
    bindFakeComposer([]);
    $existing = [
        'someOtherSetting' => true,
        'permissions' => ['ask' => ['Bash(rm:*)'], 'allow' => ['Bash(ls:*)'], 'deny' => ['Read(./.env)']],
    ];
    $this->withTempBasePath(['.claude/settings.json' => json_encode($existing)]);

    expect(makeCheck(AsksBeforeDestructiveDbCommandsInClaudeSettingsCheck::class)->fix())->toBe(CheckResult::PASS);

    $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true);
    expect($settings['someOtherSetting'])->toBeTrue();
    expect($settings['permissions']['allow'])->toBe(['Bash(ls:*)']);
    expect($settings['permissions']['deny'])->toBe(['Read(./.env)']);
    expect($settings['permissions']['ask'][0])->toBe('Bash(rm:*)');
    expect($settings['permissions']['ask'])->toContain('Bash(ddev artisan db:wipe:*)');
});

it('asksBeforeDestructiveDbCommandsInClaudeSettings fix is idempotent', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    $check = makeCheck(AsksBeforeDestructiveDbCommandsInClaudeSettingsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect($check->fix())->toBe(CheckResult::PASS);

    $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true);
    expect($settings['permissions']['ask'])->toBe(requiredAskEntries());
});
