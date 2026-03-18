<?php

use Illuminate\Support\Facades\Schedule;
use Limenet\LaravelBaseline\Checks\Checks\UsesLaravelHorizonCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesLaravelHorizon fails when package is missing', function (): void {
    bindFakeComposer(['laravel/horizon' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    expect(makeCheck(UsesLaravelHorizonCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesLaravelHorizon fails when post-deploy script is missing', function (): void {
    bindFakeComposer(['laravel/horizon' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    expect(makeCheck(UsesLaravelHorizonCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesLaravelHorizon fails when horizon:snapshot is not scheduled', function (): void {
    bindFakeComposer(['laravel/horizon' => true]);
    $composer = ['scripts' => ['ci-deploy-post' => ['php artisan horizon:terminate']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(UsesLaravelHorizonCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesLaravelHorizon passes when post-deploy script exists and snapshot is scheduled', function (): void {
    bindFakeComposer(['laravel/horizon' => true]);
    $composer = ['scripts' => ['ci-deploy-post' => ['php artisan horizon:terminate']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    Schedule::command('horizon:snapshot')->everyFiveMinutes();

    expect(makeCheck(UsesLaravelHorizonCheck::class)->check())->toBe(CheckResult::PASS);
});
