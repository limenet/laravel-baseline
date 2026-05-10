<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotUseGreaterThanOrEqualConstraintsCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('doesNotUseGreaterThanOrEqualConstraints implements FixableInterface', function (): void {
    expect(makeCheck(DoesNotUseGreaterThanOrEqualConstraintsCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('doesNotUseGreaterThanOrEqualConstraints fix replaces >= constraints with ^', function (): void {
    bindFakeComposer([]);
    $composer = [
        'require' => ['php' => '^8.3', 'vendor/pkg' => '>=1.2'],
        'require-dev' => ['other/pkg' => '>=2.0.0'],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(DoesNotUseGreaterThanOrEqualConstraintsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $updated = json_decode(file_get_contents(base_path('composer.json')), true);
    expect($updated['require']['vendor/pkg'])->toBe('^1.2');
    expect($updated['require-dev']['other/pkg'])->toBe('^2.0.0');
    expect($updated['require']['php'])->toBe('^8.3');
});

it('doesNotUseGreaterThanOrEqualConstraints fix is idempotent when already correct', function (): void {
    bindFakeComposer([]);
    $composer = ['require' => ['php' => '^8.3']];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(DoesNotUseGreaterThanOrEqualConstraintsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect($check->fix())->toBe(CheckResult::PASS);
});
