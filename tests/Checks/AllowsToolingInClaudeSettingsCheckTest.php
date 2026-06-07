<?php

use Limenet\LaravelBaseline\Checks\Checks\AllowsToolingInClaudeSettingsCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

$requiredAllow = [
    'Bash(ddev composer run ci-lint:*)',
    'Bash(ddev composer test:*)',
    'Bash(ddev artisan test:*)',
    'Bash(ddev artisan make:*)',
    'Bash(ddev artisan route:list:*)',
    'Bash(ddev artisan about:*)',
    'Bash(ddev artisan config:show:*)',
    'Bash(ddev artisan ide-helper:*)',
    'Bash(ddev artisan optimize:clear:*)',
    'Bash(ddev artisan cache:clear:*)',
    'Bash(ddev artisan config:clear:*)',
    'Bash(ddev artisan route:clear:*)',
    'Bash(ddev artisan view:clear:*)',
    'Bash(ddev composer show:*)',
    'Bash(ddev composer outdated:*)',
    'Bash(ddev composer why:*)',
    'Bash(npm info:*)',
    'Bash(npm view:*)',
    'Bash(npm ls:*)',
    'Bash(npm outdated:*)',
    'Skill(code-review)',
    'Skill(code-review:*)',
];

it('allowsToolingInClaudeSettings implements FixableInterface', function (): void {
    expect(makeCheck(AllowsToolingInClaudeSettingsCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('allowsToolingInClaudeSettings passes when all allow entries present', function () use ($requiredAllow): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.claude/settings.json' => json_encode(['permissions' => ['allow' => $requiredAllow]]),
    ]);

    expect(makeCheck(AllowsToolingInClaudeSettingsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('allowsToolingInClaudeSettings fails when settings file is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    [$check, $collector] = makeCheckWithCollector(AllowsToolingInClaudeSettingsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Claude settings: add "Bash(ddev composer run ci-lint:*)" to permissions.allow in .claude/settings.json');
});

it('allowsToolingInClaudeSettings fails when settings file is empty', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['.claude/settings.json' => '']);

    expect(makeCheck(AllowsToolingInClaudeSettingsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('allowsToolingInClaudeSettings fails when an allow entry is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        '.claude/settings.json' => json_encode(['permissions' => ['allow' => ['Bash(ddev composer run ci-lint:*)', 'Bash(ddev composer test:*)']]]),
    ]);

    [$check, $collector] = makeCheckWithCollector(AllowsToolingInClaudeSettingsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Claude settings: add "Bash(ddev artisan test:*)" to permissions.allow in .claude/settings.json');
});

it('allowsToolingInClaudeSettings fix creates settings file when missing', function () use ($requiredAllow): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    expect(makeCheck(AllowsToolingInClaudeSettingsCheck::class)->fix())->toBe(CheckResult::PASS);

    $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true);
    expect($settings['permissions']['allow'])->toBe($requiredAllow);
});

it('allowsToolingInClaudeSettings fix merges into existing settings preserving other entries', function (): void {
    bindFakeComposer([]);
    $existing = [
        'permissions' => ['allow' => ['Bash(ls:*)'], 'deny' => ['Read(./.env)']],
    ];
    $this->withTempBasePath(['.claude/settings.json' => json_encode($existing)]);

    expect(makeCheck(AllowsToolingInClaudeSettingsCheck::class)->fix())->toBe(CheckResult::PASS);

    $settings = json_decode(file_get_contents(base_path('.claude/settings.json')), true);
    expect($settings['permissions']['deny'])->toBe(['Read(./.env)']);
    expect($settings['permissions']['allow'])->toContain('Bash(ls:*)');
    expect($settings['permissions']['allow'])->toContain('Bash(ddev artisan test:*)');
    expect($settings['permissions']['allow'])->not->toContain('Bash(ddev artisan:*)');
});

it('allowsToolingInClaudeSettings fix is idempotent', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    $check = makeCheck(AllowsToolingInClaudeSettingsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect($check->fix())->toBe(CheckResult::PASS);
});
