<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesPhpInsightsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

function composerWithScripts(): array
{
    return [
        'scripts' => [
            'ci-lint' => [
                'insights --summary --no-interaction',
                'insights -n --ansi --format=codeclimate > codeclimate-report.json 2>/dev/null',
            ],
        ],
    ];
}

function insightsConfigWith(bool $disableSecurityCheck): string
{
    $value = $disableSecurityCheck ? 'true' : 'false';

    return <<<PHP
<?php
return [
    'preset' => 'laravel',
    'requirements' => [
        'min-quality' => 91,
        'disable-security-check' => {$value},
    ],
];
PHP;
}

it('usesPhpInsights fails when package not installed', function (): void {
    bindFakeComposer(['nunomaduro/phpinsights' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesPhpInsightsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesPhpInsights fails when ci-lint scripts missing', function (): void {
    bindFakeComposer(['nunomaduro/phpinsights' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['scripts' => []])]);

    expect(makeCheck(UsesPhpInsightsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesPhpInsights fails when config/insights.php missing', function (): void {
    bindFakeComposer(['nunomaduro/phpinsights' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(composerWithScripts())]);

    expect(makeCheck(UsesPhpInsightsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesPhpInsights fails when disable-security-check is false', function (): void {
    bindFakeComposer(['nunomaduro/phpinsights' => true]);
    $this->withTempBasePath([
        'composer.json' => json_encode(composerWithScripts()),
        'config/insights.php' => insightsConfigWith(false),
    ]);

    expect(makeCheck(UsesPhpInsightsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('usesPhpInsights passes when scripts and disable-security-check are configured', function (): void {
    bindFakeComposer(['nunomaduro/phpinsights' => true]);
    $this->withTempBasePath([
        'composer.json' => json_encode(composerWithScripts()),
        'config/insights.php' => insightsConfigWith(true),
    ]);

    expect(makeCheck(UsesPhpInsightsCheck::class)->check())->toBe(CheckResult::PASS);
});

it('usesPhpInsights provides comment when disable-security-check is not true', function (): void {
    bindFakeComposer(['nunomaduro/phpinsights' => true]);
    $this->withTempBasePath([
        'composer.json' => json_encode(composerWithScripts()),
        'config/insights.php' => insightsConfigWith(false),
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesPhpInsightsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Set 'disable-security-check' => true in the requirements section of config/insights.php");
});
