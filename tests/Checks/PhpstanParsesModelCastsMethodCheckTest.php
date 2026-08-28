<?php

use Limenet\LaravelBaseline\Checks\Checks\PhpstanParsesModelCastsMethodCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

/**
 * @param  string|null  $phpstanNeon  the phpstan.neon body, or null to omit the file
 */
function phpstanCastsProject(?string $phpstanNeon): void
{
    $files = ['composer.json' => json_encode(['name' => 'tmp/app'])];

    if ($phpstanNeon !== null) {
        $files['phpstan.neon'] = $phpstanNeon;
    }

    test()->withTempBasePath($files);
}

it('phpstanParsesModelCastsMethod fails when phpstan.neon is missing', function (): void {
    phpstanCastsProject(null);

    $check = makeCheck(PhpstanParsesModelCastsMethodCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('PHPStan configuration missing: Create phpstan.neon in project root');
});

it('phpstanParsesModelCastsMethod passes when the parameter is set to true', function (): void {
    phpstanCastsProject(<<<'NEON'
    parameters:
        level: max
        checkModelProperties: true
        parseModelCastsMethod: true
    NEON);

    expect(makeCheck(PhpstanParsesModelCastsMethodCheck::class)->check())->toBe(CheckResult::PASS);
});

it('phpstanParsesModelCastsMethod fails when the parameter is absent', function (): void {
    phpstanCastsProject(<<<'NEON'
    parameters:
        level: max
        checkModelProperties: true
    NEON);

    $check = makeCheck(PhpstanParsesModelCastsMethodCheck::class);

    expect($check->check())->toBe(CheckResult::FAIL);
    expect(implode("\n", $check->getComments()))->toContain('PHPStan does not read the casts() method');
});

it('phpstanParsesModelCastsMethod fails when the parameter is explicitly false', function (): void {
    phpstanCastsProject(<<<'NEON'
    parameters:
        parseModelCastsMethod: false
    NEON);

    expect(makeCheck(PhpstanParsesModelCastsMethodCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('phpstanParsesModelCastsMethod adds the parameter to an existing parameters block', function (): void {
    phpstanCastsProject(<<<'NEON'
    includes:
        - vendor/larastan/larastan/extension.neon

    parameters:
        level: max
        # keep this comment
        checkModelProperties: true
    NEON);

    expect(makeCheck(PhpstanParsesModelCastsMethodCheck::class)->fix())->toBe(CheckResult::PASS);

    $contents = (string) file_get_contents(base_path('phpstan.neon'));

    expect($contents)->toContain('    parseModelCastsMethod: true');
    expect($contents)->toContain('# keep this comment');
    expect($contents)->toContain('- vendor/larastan/larastan/extension.neon');
    expect(substr_count($contents, 'parameters:'))->toBe(1);
});

it('phpstanParsesModelCastsMethod flips an explicit false to true', function (): void {
    phpstanCastsProject(<<<'NEON'
    parameters:
        level: max
        parseModelCastsMethod: false
    NEON);

    expect(makeCheck(PhpstanParsesModelCastsMethodCheck::class)->fix())->toBe(CheckResult::PASS);
    expect((string) file_get_contents(base_path('phpstan.neon')))
        ->toContain('parseModelCastsMethod: true')
        ->not->toContain('parseModelCastsMethod: false');
});

it('phpstanParsesModelCastsMethod creates the parameters block when the file has none', function (): void {
    phpstanCastsProject(<<<'NEON'
    includes:
        - vendor/larastan/larastan/extension.neon
    NEON);

    expect(makeCheck(PhpstanParsesModelCastsMethodCheck::class)->fix())->toBe(CheckResult::PASS);
    expect((string) file_get_contents(base_path('phpstan.neon')))->toContain("parameters:\n    parseModelCastsMethod: true");
});

it('phpstanParsesModelCastsMethod does not write when phpstan.neon is missing', function (): void {
    phpstanCastsProject(null);

    expect(makeCheck(PhpstanParsesModelCastsMethodCheck::class)->fix())->toBe(CheckResult::FAIL);
    expect(file_exists(base_path('phpstan.neon')))->toBeFalse();
});
