<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesIdeHelpersCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesIdeHelpers passes with package and post-update scripts', function (): void {
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
    expect($check->check())->toBe(CheckResult::PASS);
});
