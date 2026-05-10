<?php

use Limenet\LaravelBaseline\Checks\Checks\IsInstalledAsRegularDependencyCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('isInstalledAsRegularDependency implements FixableInterface', function (): void {
    expect(makeCheck(IsInstalledAsRegularDependencyCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('isInstalledAsRegularDependency fix moves package from require-dev to require', function (): void {
    bindFakeComposer([]);
    $composer = [
        'require' => ['php' => '^8.3'],
        'require-dev' => ['limenet/laravel-baseline' => '^1.0'],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(IsInstalledAsRegularDependencyCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $updated = json_decode(file_get_contents(base_path('composer.json')), true);
    expect(isset($updated['require']['limenet/laravel-baseline']))->toBeTrue();
    expect(isset($updated['require-dev']['limenet/laravel-baseline']))->toBeFalse();
});

it('isInstalledAsRegularDependency fix passes when already in require', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['limenet/laravel-baseline' => '^1.0']];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(IsInstalledAsRegularDependencyCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
});
