<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotCallPeriodicBaselineOnUpdateCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

it('doesNotCallPeriodicBaselineOnUpdate passes when post-update script is absent', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    expect(makeCheck(DoesNotCallPeriodicBaselineOnUpdateCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotCallPeriodicBaselineOnUpdate fails when post-update script is present', function (): void {
    bindFakeComposer([]);
    $composer = ['scripts' => ['post-update-cmd' => ['php artisan limenet:laravel-baseline:periodic']]];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(DoesNotCallPeriodicBaselineOnUpdateCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('doesNotCallPeriodicBaselineOnUpdate flags the standalone periodic command', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode([
        'scripts' => ['post-update-cmd' => ['@php vendor/bin/baseline periodic']],
    ])]);

    expect(makeCheck(DoesNotCallPeriodicBaselineOnUpdateCheck::class, $project)->check())->toBe(CheckResult::FAIL);
});

it('doesNotCallPeriodicBaselineOnUpdate passes on the standalone check command', function (): void {
    $project = makeProject(Profile::WordPress, ['composer.json' => json_encode([
        'scripts' => ['post-update-cmd' => ['@php vendor/bin/baseline check --fix']],
    ])]);

    expect(makeCheck(DoesNotCallPeriodicBaselineOnUpdateCheck::class, $project)->check())->toBe(CheckResult::PASS);
});
