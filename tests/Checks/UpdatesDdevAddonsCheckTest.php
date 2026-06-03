<?php

use Limenet\LaravelBaseline\Checks\Checks\UpdatesDdevAddonsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

function ddevAddonManifest(string $repository, string $installDate, string $name = 'some-addon'): string
{
    return <<<YML
        name: {$name}
        repository: {$repository}
        version: v1.0.9
        install_date: "{$installDate}"
        YML;
}

it('updatesDdevAddons passes when no addons are installed', function (): void {
    $this->withTempBasePath([]);

    expect(makeCheck(UpdatesDdevAddonsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('updatesDdevAddons passes when addon was installed recently', function (): void {
    $this->withTempBasePath([
        '.ddev/addon-metadata/playwright/manifest.yaml' => ddevAddonManifest(
            'codingsasi/ddev-playwright',
            now()->subMonth()->toAtomString(),
        ),
    ]);

    expect(makeCheck(UpdatesDdevAddonsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('updatesDdevAddons fails when addon is older than 3 months', function (): void {
    $this->withTempBasePath([
        '.ddev/addon-metadata/playwright/manifest.yaml' => ddevAddonManifest(
            'codingsasi/ddev-playwright',
            now()->subMonths(4)->toAtomString(),
        ),
    ]);

    expect(makeCheck(UpdatesDdevAddonsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('updatesDdevAddons provides comment with update command when addon is outdated', function (): void {
    $this->withTempBasePath([
        '.ddev/addon-metadata/playwright/manifest.yaml' => ddevAddonManifest(
            'codingsasi/ddev-playwright',
            now()->subMonths(4)->toAtomString(),
            'ddev-playwright',
        ),
    ]);

    [$check, $collector] = makeCheckWithCollector(UpdatesDdevAddonsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $collector->all()))->toContain('ddev add-on get codingsasi/ddev-playwright');
});

it('updatesDdevAddons fails when at least one of multiple addons is outdated', function (): void {
    $this->withTempBasePath([
        '.ddev/addon-metadata/playwright/manifest.yaml' => ddevAddonManifest(
            'codingsasi/ddev-playwright',
            now()->subMonth()->toAtomString(),
        ),
        '.ddev/addon-metadata/redis/manifest.yaml' => ddevAddonManifest(
            'ddev/ddev-redis',
            now()->subMonths(5)->toAtomString(),
        ),
    ]);

    [$check, $collector] = makeCheckWithCollector(UpdatesDdevAddonsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    $comments = implode("\n", $collector->all());
    expect($comments)->toContain('ddev add-on get ddev/ddev-redis');
    expect($comments)->not->toContain('ddev add-on get codingsasi/ddev-playwright');
});

it('updatesDdevAddons skips manifests without an install_date', function (): void {
    $this->withTempBasePath([
        '.ddev/addon-metadata/playwright/manifest.yaml' => <<<'YML'
            name: ddev-playwright
            repository: codingsasi/ddev-playwright
            version: v1.0.9
            YML,
    ]);

    expect(makeCheck(UpdatesDdevAddonsCheck::class)->check())->toBe(CheckResult::PASS);
});
