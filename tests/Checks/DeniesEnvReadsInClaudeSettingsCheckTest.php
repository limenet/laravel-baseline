<?php

use Limenet\LaravelBaseline\Checks\Checks\DeniesEnvReadsInClaudeSettingsCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('deniesEnvReadsInClaudeSettings implements FixableInterface', function (): void {
    expect(makeCheck(DeniesEnvReadsInClaudeSettingsCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('deniesEnvReadsInClaudeSettings passes with only base .env when no encrypted envs exist', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.claude/settings.json' => json_encode(['permissions' => ['deny' => ['Read(./.env)']]]),
    ]);

    expect(makeCheck(DeniesEnvReadsInClaudeSettingsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('deniesEnvReadsInClaudeSettings requires denying each environment with an encrypted file', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.env.staging.encrypted' => 'x',
        '.env.production.encrypted' => 'x',
        '.claude/settings.json' => json_encode(['permissions' => ['deny' => ['Read(./.env)']]]),
    ]);

    [$check, $collector] = makeCheckWithCollector(DeniesEnvReadsInClaudeSettingsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Claude settings: add "Read(./.env.staging)" to permissions.deny in .claude/settings.json — prevents Claude from reading secrets');
    expect($collector->all())->toContain('Claude settings: add "Read(./.env.production)" to permissions.deny in .claude/settings.json — prevents Claude from reading secrets');
});

it('deniesEnvReadsInClaudeSettings passes when every encrypted environment is denied', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.env.staging.encrypted' => 'x',
        '.claude/settings.json' => json_encode(['permissions' => ['deny' => ['Read(./.env)', 'Read(./.env.staging)']]]),
    ]);

    expect(makeCheck(DeniesEnvReadsInClaudeSettingsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('deniesEnvReadsInClaudeSettings fails when settings file is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    [$check, $collector] = makeCheckWithCollector(DeniesEnvReadsInClaudeSettingsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Claude settings: add "Read(./.env)" to permissions.deny in .claude/settings.json — prevents Claude from reading secrets');
});

it('deniesEnvReadsInClaudeSettings fails when settings file is empty', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['.claude/settings.json' => '']);

    expect(makeCheck(DeniesEnvReadsInClaudeSettingsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('deniesEnvReadsInClaudeSettings does not require denying .env.example', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.env.example' => 'x',
        '.claude/settings.json' => json_encode(['permissions' => ['deny' => ['Read(./.env)']]]),
    ]);

    $check = makeCheck(DeniesEnvReadsInClaudeSettingsCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
    expect($check->getComments())->not->toContain('Claude settings: add "Read(./.env.example)" to permissions.deny in .claude/settings.json — prevents Claude from reading secrets');
});

it('deniesEnvReadsInClaudeSettings fix creates settings denying base .env when no encrypted envs exist', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    expect(makeCheck(DeniesEnvReadsInClaudeSettingsCheck::class)->fix())->toBe(CheckResult::PASS);

    $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true);
    expect($settings['permissions']['deny'])->toBe(['Read(./.env)']);
});

it('deniesEnvReadsInClaudeSettings fix denies each discovered encrypted environment', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.env.staging.encrypted' => 'x',
        '.env.production.encrypted' => 'x',
    ]);

    expect(makeCheck(DeniesEnvReadsInClaudeSettingsCheck::class)->fix())->toBe(CheckResult::PASS);

    $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true);
    expect($settings['permissions']['deny'])->toContain('Read(./.env)');
    expect($settings['permissions']['deny'])->toContain('Read(./.env.staging)');
    expect($settings['permissions']['deny'])->toContain('Read(./.env.production)');
    expect($settings['permissions']['deny'])->not->toContain('Read(./.env.example)');
});

it('deniesEnvReadsInClaudeSettings fix merges into existing settings preserving other entries', function (): void {
    bindFakeComposer([]);
    $existing = [
        'someOtherSetting' => true,
        'permissions' => ['deny' => ['Read(./secrets/**)'], 'allow' => ['Bash(ls:*)']],
    ];
    $this->withTempBasePath([
        '.env.staging.encrypted' => 'x',
        '.claude/settings.json' => json_encode($existing),
    ]);

    expect(makeCheck(DeniesEnvReadsInClaudeSettingsCheck::class)->fix())->toBe(CheckResult::PASS);

    $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true);
    expect($settings['someOtherSetting'])->toBeTrue();
    expect($settings['permissions']['allow'])->toBe(['Bash(ls:*)']);
    expect($settings['permissions']['deny'])->toContain('Read(./secrets/**)');
    expect($settings['permissions']['deny'])->toContain('Read(./.env)');
    expect($settings['permissions']['deny'])->toContain('Read(./.env.staging)');
});

it('deniesEnvReadsInClaudeSettings fix is idempotent', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.env.staging.encrypted' => 'x',
    ]);

    $check = makeCheck(DeniesEnvReadsInClaudeSettingsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect($check->fix())->toBe(CheckResult::PASS);
});
