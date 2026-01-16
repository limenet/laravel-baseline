<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesPestCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesPest passes when pest packages are present and no disallowed packages', function (): void {
    bindFakeComposer([
        'pestphp/pest' => true,
        'pestphp/pest-plugin-laravel' => true,
        'pestphp/pest-plugin-drift' => false,
        'spatie/phpunit-watcher' => false,
    ]);

    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesPestCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('usesPest fails only when both drift plugin and phpunit-watcher are present (current behavior)', function (): void {
    bindFakeComposer([
        'pestphp/pest' => true,
        'pestphp/pest-plugin-laravel' => true,
        'pestphp/pest-plugin-drift' => true, // disallowed
        'spatie/phpunit-watcher' => true,    // disallowed
    ]);

    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesPestCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesPest still passes when only one of drift or phpunit-watcher is present (documenting current behavior)', function (): void {
    bindFakeComposer([
        'pestphp/pest' => true,
        'pestphp/pest-plugin-laravel' => true,
        'pestphp/pest-plugin-drift' => true, // one present
        'spatie/phpunit-watcher' => false,
    ]);

    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesPestCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
