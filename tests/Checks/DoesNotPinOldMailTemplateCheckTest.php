<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotPinOldMailTemplateCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('doesNotPinOldMailTemplate passes when no published mail theme exists', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([]);

    $check = makeCheck(DoesNotPinOldMailTemplateCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('doesNotPinOldMailTemplate fails when the published default mail theme exists', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([
        'resources/views/vendor/mail/html/themes/default.css' => '/* custom */',
    ]);

    $check = makeCheck(DoesNotPinOldMailTemplateCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('doesNotPinOldMailTemplate fails when only the published header view exists', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([
        'resources/views/vendor/mail/html/header.blade.php' => '<x-slot:header />',
    ]);

    $check = makeCheck(DoesNotPinOldMailTemplateCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('doesNotPinOldMailTemplate provides a helpful comment when the theme is pinned', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([
        'resources/views/vendor/mail/html/themes/default.css' => '/* custom */',
    ]);

    [$check, $collector] = makeCheckWithCollector(DoesNotPinOldMailTemplateCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect(collect($collector->all())->contains(fn ($c) => str_contains($c, 'pins the old template')))->toBeTrue();
});
