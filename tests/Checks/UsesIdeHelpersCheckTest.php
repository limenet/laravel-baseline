<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesIdeHelpersCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

function canonicalIdeHelpersComposerJson(): string
{
    return json_encode([
        'scripts' => [
            'post-update-cmd' => [
                'php artisan ide-helper:generate',
                'php artisan ide-helper:models --nowrite',
                'php artisan ide-helper:meta',
            ],
        ],
    ]);
}

it('usesIdeHelpers passes with package, post-update scripts, and gitignore entries', function (): void {
    bindFakeComposer(['barryvdh/laravel-ide-helper' => true]);

    $this->withTempBasePath([
        'composer.json' => canonicalIdeHelpersComposerJson(),
        '.gitignore' => "_ide_helper.php\n_ide_helper_models.php\n.phpstorm.meta.php\n",
    ]);

    $check = makeCheck(UsesIdeHelpersCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('usesIdeHelpers fails when package is missing', function (): void {
    bindFakeComposer(['barryvdh/laravel-ide-helper' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesIdeHelpersCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesIdeHelpers fails when ide-helper:models script is missing', function (): void {
    bindFakeComposer(['barryvdh/laravel-ide-helper' => true]);
    $composer = [
        'scripts' => [
            'post-update-cmd' => [
                'php artisan ide-helper:generate',
                'php artisan ide-helper:meta',
            ],
        ],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(UsesIdeHelpersCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesIdeHelpers fails when .gitignore is absent', function (): void {
    bindFakeComposer(['barryvdh/laravel-ide-helper' => true]);
    $this->withTempBasePath(['composer.json' => canonicalIdeHelpersComposerJson()]);

    [$check, $collector] = makeCheckWithCollector(UsesIdeHelpersCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing .gitignore in project root: create it and add '_ide_helper.php'");
});

it('usesIdeHelpers fails when .gitignore is present but missing generated file entries', function (): void {
    bindFakeComposer(['barryvdh/laravel-ide-helper' => true]);
    $this->withTempBasePath([
        'composer.json' => canonicalIdeHelpersComposerJson(),
        '.gitignore' => "vendor/\nnode_modules/\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesIdeHelpersCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing entry in .gitignore: add '_ide_helper.php' to ignore generated IDE Helper files");
});

it('usesIdeHelpers fails when .gitignore is missing only one generated file entry', function (): void {
    bindFakeComposer(['barryvdh/laravel-ide-helper' => true]);
    $this->withTempBasePath([
        'composer.json' => canonicalIdeHelpersComposerJson(),
        '.gitignore' => "_ide_helper.php\n.phpstorm.meta.php\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesIdeHelpersCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing entry in .gitignore: add '_ide_helper_models.php' to ignore generated IDE Helper files");
});
