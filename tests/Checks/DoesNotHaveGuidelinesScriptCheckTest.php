<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotHaveGuidelinesScriptCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('doesNotHaveGuidelinesScript passes when post-update-cmd is absent', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    expect(makeCheck(DoesNotHaveGuidelinesScriptCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotHaveGuidelinesScript passes when post-update-cmd does not contain the script', function (): void {
    bindFakeComposer([]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan limenet:laravel-baseline:check']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(DoesNotHaveGuidelinesScriptCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotHaveGuidelinesScript fails when post-update-cmd contains the removed script', function (): void {
    bindFakeComposer([]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan limenet:laravel-baseline:guidelines']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    [$check, $collector] = makeCheckWithCollector(DoesNotHaveGuidelinesScriptCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Remove "php artisan limenet:laravel-baseline:guidelines" from post-update-cmd in composer.json — the command was removed in v2.1.0 and no longer exists');
});

it('doesNotHaveGuidelinesScript fix removes the stale entry from composer.json', function (): void {
    bindFakeComposer([]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan limenet:laravel-baseline:guidelines', 'php artisan limenet:laravel-baseline:check --fix']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(DoesNotHaveGuidelinesScriptCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $written = json_decode(file_get_contents(base_path('composer.json')), true);
    expect($written['scripts']['post-update-cmd'])->not->toContain('php artisan limenet:laravel-baseline:guidelines');
    expect($written['scripts']['post-update-cmd'])->toContain('php artisan limenet:laravel-baseline:check --fix');
});
