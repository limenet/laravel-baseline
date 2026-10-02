<?php

use Limenet\LaravelBaseline\Checks\Checks\CallsBaselineCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

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

it('callsBaseline wires vendor/bin/baseline into post-update-cmd outside Laravel', function (Profile $profile): void {
    $project = makeProject($profile, ['composer.json' => json_encode(['scripts' => ['post-update-cmd' => ['@composer bump']]])]);

    $check = makeCheck(CallsBaselineCheck::class, $project);

    expect($check->check())->toBe(CheckResult::FAIL)
        ->and($check->fix())->toBe(CheckResult::PASS)
        ->and(json_decode((string) file_get_contents($project->path('composer.json')), true)['scripts']['post-update-cmd'])
        ->toBe(['@composer bump', '@php vendor/bin/baseline check --fix']);
})->with([Profile::Php, Profile::WordPress]);

it('callsBaseline upgrades a standalone entry without --fix', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode(['scripts' => ['post-update-cmd' => ['vendor/bin/baseline check']]])]);

    expect(makeCheck(CallsBaselineCheck::class, $project)->fix())->toBe(CheckResult::PASS)
        ->and(json_decode((string) file_get_contents($project->path('composer.json')), true)['scripts']['post-update-cmd'])
        ->toBe(['vendor/bin/baseline check --fix']);
});

it('callsBaseline does not accept the artisan command outside Laravel', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode([
        'scripts' => ['post-update-cmd' => ['@php artisan limenet:laravel-baseline:check --fix']],
    ])]);

    expect(makeCheck(CallsBaselineCheck::class, $project)->check())->toBe(CheckResult::FAIL);
});

it('callsBaseline upgrades a post-update-cmd given as a single string', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode(['scripts' => ['post-update-cmd' => '@php vendor/bin/baseline check']])]);

    expect(makeCheck(CallsBaselineCheck::class, $project)->fix())->toBe(CheckResult::PASS)
        ->and(json_decode((string) file_get_contents($project->path('composer.json')), true)['scripts']['post-update-cmd'])
        ->toBe(['@php vendor/bin/baseline check --fix']);
});

it('callsBaseline appends to a post-update-cmd given as a single string', function (): void {
    $project = makeProject(Profile::Php, ['composer.json' => json_encode(['scripts' => ['post-update-cmd' => '@composer bump']])]);

    expect(makeCheck(CallsBaselineCheck::class, $project)->fix())->toBe(CheckResult::PASS)
        ->and(json_decode((string) file_get_contents($project->path('composer.json')), true)['scripts']['post-update-cmd'])
        ->toBe(['@composer bump', '@php vendor/bin/baseline check --fix']);
});
