<?php

use Limenet\LaravelBaseline\Checks\Checks\HasRectorConfigWithImportNamesCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

$validRector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;
use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;
use Limenet\LaravelBaseline\Rector\LaravelBaselineSetList;

return static function (RectorConfig $config): void {
    $config
        ->withPaths([__DIR__.'/app', __DIR__.'/database', __DIR__.'/routes', __DIR__.'/tests'])
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(deadCode: true, codeQuality: true, codingStyle: true, typeDeclarations: true, privatization: true, instanceOf: true, earlyReturn: true)
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false)
        ->withRules([AddGenericReturnTypeToRelationsRector::class])
        ->withSets([LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS]);
    $config->withSetProviders(LaravelSetProvider::class);
};
PHP;

it('hasRectorConfigWithImportNames fails when rector.php is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(HasRectorConfigWithImportNamesCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('hasRectorConfigWithImportNames fails when withImportNames is absent', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
return static function (RectorConfig $config): void {
    $config->withPhpSets();
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(HasRectorConfigWithImportNamesCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('hasRectorConfigWithImportNames passes when correctly configured', function () use ($validRector): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $validRector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(HasRectorConfigWithImportNamesCheck::class)->check())->toBe(CheckResult::PASS);
});
