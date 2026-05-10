<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotUseSpatiePasskeysWithFortifyCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('doesNotUseSpatiePasskeysWithFortify passes when fortify is not installed', function (): void {
    bindFakeComposer(['laravel/fortify' => false, 'spatie/laravel-passkeys' => false]);

    $check = makeCheck(DoesNotUseSpatiePasskeysWithFortifyCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('doesNotUseSpatiePasskeysWithFortify passes when fortify is installed but passkeys is not', function (): void {
    bindFakeComposer(['laravel/fortify' => true, 'spatie/laravel-passkeys' => false]);

    $check = makeCheck(DoesNotUseSpatiePasskeysWithFortifyCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('doesNotUseSpatiePasskeysWithFortify fails when both fortify and passkeys are installed', function (): void {
    bindFakeComposer(['laravel/fortify' => true, 'spatie/laravel-passkeys' => true]);

    [$check, $collector] = makeCheckWithCollector(DoesNotUseSpatiePasskeysWithFortifyCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Remove spatie/laravel-passkeys: it overlaps with laravel/fortify which is already installed.');
});
