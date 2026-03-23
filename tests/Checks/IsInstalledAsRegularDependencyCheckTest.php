<?php

use Limenet\LaravelBaseline\Checks\Checks\IsInstalledAsRegularDependencyCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('isInstalledAsRegularDependency fails when package is missing', function (): void {
    $this->withTempBasePath(['composer.json' => json_encode(['require' => []])]);

    expect(makeCheck(IsInstalledAsRegularDependencyCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('isInstalledAsRegularDependency fails when package is in require-dev', function (): void {
    $composer = ['require' => [], 'require-dev' => ['limenet/laravel-baseline' => '*']];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    [$check, $collector] = makeCheckWithCollector(IsInstalledAsRegularDependencyCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('limenet/laravel-baseline is in require-dev: Move it to require in composer.json');
});

it('isInstalledAsRegularDependency passes when package is in require', function (): void {
    $composer = ['require' => ['limenet/laravel-baseline' => '*']];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(IsInstalledAsRegularDependencyCheck::class)->check())->toBe(CheckResult::PASS);
});
