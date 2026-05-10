<?php

use Limenet\LaravelBaseline\Checks\Checks\BumpsComposerCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('bumpsComposer implements FixableInterface', function (): void {
    expect(makeCheck(BumpsComposerCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('bumpsComposer fix adds composer bump to post-update-cmd', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(BumpsComposerCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $updated = json_decode(file_get_contents(base_path('composer.json')), true);
    expect(implode(' ', $updated['scripts']['post-update-cmd']))->toContain('composer bump');
});

it('bumpsComposer fix is idempotent', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(BumpsComposerCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect($check->fix())->toBe(CheckResult::PASS);

    $updated = json_decode(file_get_contents(base_path('composer.json')), true);
    expect(count($updated['scripts']['post-update-cmd']))->toBe(1);
});
