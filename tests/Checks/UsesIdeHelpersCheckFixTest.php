<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesIdeHelpersCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesIdeHelpers implements FixableInterface', function (): void {
    expect(makeCheck(UsesIdeHelpersCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('usesIdeHelpers fix inserts the models script between existing generate and meta scripts', function (): void {
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

    $check = makeCheck(UsesIdeHelpersCheck::class);
    $check->fix();

    $updated = json_decode(file_get_contents(base_path('composer.json')), true);
    expect($updated['scripts']['post-update-cmd'])->toBe([
        'php artisan ide-helper:generate',
        '@php artisan ide-helper:models --nowrite',
        'php artisan ide-helper:meta',
    ]);
});

it('usesIdeHelpers fix appends all three scripts when none are present', function (): void {
    bindFakeComposer(['barryvdh/laravel-ide-helper' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesIdeHelpersCheck::class);
    $check->fix();

    $updated = json_decode(file_get_contents(base_path('composer.json')), true);
    expect($updated['scripts']['post-update-cmd'])->toBe([
        '@php artisan ide-helper:generate',
        '@php artisan ide-helper:models --nowrite',
        '@php artisan ide-helper:meta',
    ]);
});

it('usesIdeHelpers fix creates .gitignore with generated file entries when missing', function (): void {
    bindFakeComposer(['barryvdh/laravel-ide-helper' => true]);
    $composer = [
        'scripts' => [
            'post-update-cmd' => [
                'php artisan ide-helper:generate',
                'php artisan ide-helper:models --nowrite',
                'php artisan ide-helper:meta',
            ],
        ],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(UsesIdeHelpersCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $gitignore = file_get_contents(base_path('.gitignore'));
    expect($gitignore)->toContain('_ide_helper.php');
    expect($gitignore)->toContain('_ide_helper_models.php');
    expect($gitignore)->toContain('.phpstorm.meta.php');
});

it('usesIdeHelpers fix bootstraps from nothing in a single call', function (): void {
    bindFakeComposer(['barryvdh/laravel-ide-helper' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesIdeHelpersCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect(makeCheck(UsesIdeHelpersCheck::class)->check())->toBe(CheckResult::PASS);
});
