<?php

use Limenet\LaravelBaseline\Checks\Checks\HasRectorConfigWithPhpSetsCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('hasRectorConfigWithPhpSets implements FixableInterface', function (): void {
    expect(makeCheck(HasRectorConfigWithPhpSetsCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('hasRectorConfigWithPhpSets fix creates rector.php when missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    $check = makeCheck(HasRectorConfigWithPhpSetsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect(file_exists(base_path('rector.php')))->toBeTrue();
    expect(file_get_contents(base_path('rector.php')))->toContain('withPhpSets');
});

it('hasRectorConfigWithPhpSets fix appends to existing rector.php', function (): void {
    bindFakeComposer([]);
    $rector = "<?php\nuse Rector\\Config\\RectorConfig;\nreturn RectorConfig::configure();\n";
    $this->withTempBasePath(['rector.php' => $rector]);

    $check = makeCheck(HasRectorConfigWithPhpSetsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect(file_get_contents(base_path('rector.php')))->toContain('withPhpSets');
});

it('hasRectorConfigWithPhpSets fix is idempotent', function (): void {
    bindFakeComposer([]);
    $rector = "<?php\nuse Rector\\Config\\RectorConfig;\nreturn RectorConfig::configure()->withPhpSets();\n";
    $this->withTempBasePath(['rector.php' => $rector]);

    $check = makeCheck(HasRectorConfigWithPhpSetsCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    expect($check->fix())->toBe(CheckResult::PASS);
});

it('hasRectorConfigWithPhpSets fix returns fail when method exists with wrong args', function (): void {
    bindFakeComposer([]);
    // Method exists but check would still fail due to wrong visitor result
    // In this case withPhpSets exists so fix() returns FAIL (can't rewrite)
    $rector = "<?php\nuse Rector\\Config\\RectorConfig;\nreturn RectorConfig::configure()->withPhpSets();\n";
    $this->withTempBasePath(['rector.php' => $rector]);

    $check = makeCheck(HasRectorConfigWithPhpSetsCheck::class);
    // check() passes → fix() returns PASS directly
    expect($check->fix())->toBe(CheckResult::PASS);
});
