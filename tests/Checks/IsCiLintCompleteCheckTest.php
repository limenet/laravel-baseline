<?php

use Limenet\LaravelBaseline\Checks\Checks\IsCiLintCompleteCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('isCiLintComplete checks ci-lint composer script contents', function (): void {
    bindFakeComposer([]);
    $scriptsOk = [
        'ci-lint' => [
            'pint --parallel',
            'phpstan',
        ],
    ];
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => $scriptsOk])]);

    $check = makeCheck(IsCiLintCompleteCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);

    $scriptsBad = ['ci-lint' => ['pint --parallel']];
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => $scriptsBad])]);

    $check = makeCheck(IsCiLintCompleteCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});
