<?php

use Limenet\LaravelBaseline\Checks\Checks\BumpsComposerCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('bumpsComposer passes when composer bump is in post-update scripts', function (): void {
    bindFakeComposer([]);
    $composer = ['scripts' => ['post-update-cmd' => ['composer bump']]];

    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(BumpsComposerCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
