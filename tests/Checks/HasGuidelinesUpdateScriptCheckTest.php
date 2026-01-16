<?php

use Limenet\LaravelBaseline\Checks\Checks\HasGuidelinesUpdateScriptCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('hasGuidelinesUpdateScript passes when guidelines update script is in post-update-cmd', function (): void {
    bindFakeComposer([]);
    $composer = [
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan limenet:laravel-baseline:guidelines',
            ],
        ],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(HasGuidelinesUpdateScriptCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('hasGuidelinesUpdateScript fails when guidelines update script is missing', function (): void {
    bindFakeComposer([]);
    $composer = [
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan ide-helper:generate',
            ],
        ],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(HasGuidelinesUpdateScriptCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('hasGuidelinesUpdateScript fails when composer.json is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    $check = makeCheck(HasGuidelinesUpdateScriptCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('hasGuidelinesUpdateScript fails when post-update-cmd section is missing', function (): void {
    bindFakeComposer([]);
    $composer = [
        'scripts' => [],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(HasGuidelinesUpdateScriptCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('hasGuidelinesUpdateScript provides helpful comment when script is missing', function (): void {
    bindFakeComposer([]);
    $composer = [
        'scripts' => [
            'post-update-cmd' => [],
        ],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(HasGuidelinesUpdateScriptCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('Missing guidelines update script in composer.json: Add "@php artisan limenet:laravel-baseline:guidelines" to post-update-cmd section');
});

it('hasGuidelinesUpdateScript passes when guidelines comes before boost:update', function (): void {
    bindFakeComposer([]);
    $composer = [
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan limenet:laravel-baseline:guidelines',
                '@php artisan boost:update',
            ],
        ],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(HasGuidelinesUpdateScriptCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('hasGuidelinesUpdateScript fails when guidelines comes after boost:update', function (): void {
    bindFakeComposer([]);
    $composer = [
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan boost:update',
                '@php artisan limenet:laravel-baseline:guidelines',
            ],
        ],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(HasGuidelinesUpdateScriptCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toContain('Guidelines update script must be called before boost:update in composer.json post-update-cmd section');
});

it('hasGuidelinesUpdateScript passes when only guidelines exists without boost', function (): void {
    bindFakeComposer([]);
    $composer = [
        'scripts' => [
            'post-update-cmd' => [
                '@php artisan limenet:laravel-baseline:guidelines',
                '@php artisan ide-helper:generate',
            ],
        ],
    ];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(HasGuidelinesUpdateScriptCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
