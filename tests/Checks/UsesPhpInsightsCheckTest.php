<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesPhpInsightsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesPhpInsights passes only when phpinsights is installed and ci-lint scripts are configured', function (): void {
    // FAIL when package not installed
    bindFakeComposer(['nunomaduro/phpinsights' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesPhpInsightsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // FAIL when package installed but ci-lint scripts missing
    bindFakeComposer(['nunomaduro/phpinsights' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    $check = makeCheck(UsesPhpInsightsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    // PASS when package installed and ci-lint scripts configured
    bindFakeComposer(['nunomaduro/phpinsights' => true]);
    $composer = [
        'scripts' => [
            'ci-lint' => [
                'insights --summary --no-interaction',
                'insights -n --ansi --format=codeclimate > codeclimate-report.json 2>/dev/null',
            ],
        ],
    ];
    $this->withTempBasePath(['composer.json' => json_encode($composer)]);

    $check = makeCheck(UsesPhpInsightsCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
