<?php

use Limenet\LaravelBaseline\Enums\CheckResult;

it('returns correct icon for PASS', function (): void {
    expect(CheckResult::PASS->icon())->toBe('✅');
});

it('returns correct icon for FAIL', function (): void {
    expect(CheckResult::FAIL->icon())->toBe('❌');
});

it('returns correct icon for WARN', function (): void {
    expect(CheckResult::WARN->icon())->toBe('⚠️');
});

it('isError returns true for FAIL', function (): void {
    expect(CheckResult::FAIL->isError())->toBeTrue();
});

it('isError returns false for PASS', function (): void {
    expect(CheckResult::PASS->isError())->toBeFalse();
});

it('isError returns false for WARN', function (): void {
    expect(CheckResult::WARN->isError())->toBeFalse();
});

it('has correct value for PASS', function (): void {
    expect(CheckResult::PASS->value)->toBe('pass');
});

it('has correct value for FAIL', function (): void {
    expect(CheckResult::FAIL->value)->toBe('fail');
});

it('has correct value for WARN', function (): void {
    expect(CheckResult::WARN->value)->toBe('warn');
});
