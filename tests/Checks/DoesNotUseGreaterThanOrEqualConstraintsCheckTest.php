<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotUseGreaterThanOrEqualConstraintsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('doesNotUseGreaterThanOrEqualConstraints passes when no >= constraints exist', function (): void {
    $composer = [
        'require' => [
            'php' => '^8.2',
            'laravel/framework' => '^11.0',
        ],
        'require-dev' => [
            'pestphp/pest' => '^3.0',
        ],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(DoesNotUseGreaterThanOrEqualConstraintsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotUseGreaterThanOrEqualConstraints fails when >= constraint exists in require', function (): void {
    $composer = [
        'require' => [
            'php' => '>=8.2',
            'laravel/framework' => '^11.0',
        ],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(DoesNotUseGreaterThanOrEqualConstraintsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('doesNotUseGreaterThanOrEqualConstraints fails when >= constraint exists in require-dev', function (): void {
    $composer = [
        'require' => [
            'laravel/framework' => '^11.0',
        ],
        'require-dev' => [
            'pestphp/pest' => '>=3.0',
        ],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    expect(makeCheck(DoesNotUseGreaterThanOrEqualConstraintsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('doesNotUseGreaterThanOrEqualConstraints fails when composer.json is missing', function (): void {
    $this->withTempBasePath([]);

    expect(makeCheck(DoesNotUseGreaterThanOrEqualConstraintsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('doesNotUseGreaterThanOrEqualConstraints provides helpful comment listing violations', function (): void {
    $composer = [
        'require' => [
            'php' => '>=8.2',
            'some/package' => '>=1.0',
        ],
        'require-dev' => [
            'pestphp/pest' => '>=3.0',
        ],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    [$check, $collector] = makeCheckWithCollector(DoesNotUseGreaterThanOrEqualConstraintsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    $comments = $collector->all();
    expect($comments)->toHaveCount(1);
    expect($comments[0])->toContain('php: >=8.2');
    expect($comments[0])->toContain('some/package: >=1.0');
    expect($comments[0])->toContain('pestphp/pest: >=3.0');
    expect($comments[0])->toContain('Use "^" instead');
});
