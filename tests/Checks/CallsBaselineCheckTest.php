<?php

use Limenet\LaravelBaseline\Checks\Checks\CallsBaselineCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('callsBaseline passes when post-update script includes --fix', function (): void {
    bindFakeComposer([]);
    $composer = ['scripts' => ['post-update-cmd' => ['@php artisan limenet:laravel-baseline:check --fix']]];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(CallsBaselineCheck::class)->check())->toBe(CheckResult::PASS);
});

it('callsBaseline fails when post-update script lacks --fix', function (): void {
    bindFakeComposer([]);
    $composer = ['scripts' => ['post-update-cmd' => ['@php artisan limenet:laravel-baseline:check']]];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(CallsBaselineCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('callsBaseline fails when post-update script is absent', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    expect(makeCheck(CallsBaselineCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('callsBaseline fix adds --fix to existing entry', function (): void {
    bindFakeComposer([]);
    $composer = ['scripts' => ['post-update-cmd' => ['@php artisan limenet:laravel-baseline:check']]];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(CallsBaselineCheck::class);
    expect($check)->toBeInstanceOf(FixableInterface::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $updated = json_decode(file_get_contents(base_path('composer.json')), true);
    $scripts = $updated['scripts']['post-update-cmd'];
    expect(implode(' ', $scripts))->toContain('limenet:laravel-baseline:check --fix');
});

it('callsBaseline fix adds new entry when absent', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(CallsBaselineCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $updated = json_decode(file_get_contents(base_path('composer.json')), true);
    $scripts = $updated['scripts']['post-update-cmd'] ?? [];
    expect(implode(' ', $scripts))->toContain('limenet:laravel-baseline:check --fix');
});

it('callsBaseline fix is idempotent', function (): void {
    bindFakeComposer([]);
    $composer = ['scripts' => ['post-update-cmd' => ['@php artisan limenet:laravel-baseline:check --fix']]];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(CallsBaselineCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect($check->fix())->toBe(CheckResult::PASS);

    $updated = json_decode(file_get_contents(base_path('composer.json')), true);
    expect(count($updated['scripts']['post-update-cmd']))->toBe(1);
});
