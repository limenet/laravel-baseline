<?php

use Limenet\LaravelBaseline\Checks\Checks\HasRectorConfigWithSetsCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

$validRector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;
use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;
use Limenet\LaravelBaseline\Rector\LaravelBaselineSetList;
use RectorLaravel\Set\LaravelSetList;

return static function (RectorConfig $config): void {
    $config
        ->withPaths([__DIR__.'/app', __DIR__.'/database', __DIR__.'/routes', __DIR__.'/tests'])
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(deadCode: true, codeQuality: true, codingStyle: true, typeDeclarations: true, privatization: true, instanceOf: true, earlyReturn: true)
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false)
        ->withRules([AddGenericReturnTypeToRelationsRector::class])
        ->withSets([
            LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS,
            LaravelSetList::LARAVEL_ARRAYACCESS_TO_METHOD_CALL,
            LaravelSetList::LARAVEL_ARRAY_STR_FUNCTION_TO_STATIC_CALL,
            LaravelSetList::LARAVEL_CODE_QUALITY,
            LaravelSetList::LARAVEL_COLLECTION,
            LaravelSetList::LARAVEL_CONTAINER_STRING_TO_FULLY_QUALIFIED_NAME,
            LaravelSetList::LARAVEL_ELOQUENT_MAGIC_METHOD_TO_QUERY_BUILDER,
            LaravelSetList::LARAVEL_FACADE_ALIASES_TO_FULL_NAMES,
            LaravelSetList::LARAVEL_LEGACY_FACTORIES_TO_CLASSES,
            LaravelSetList::LARAVEL_TESTING,
            LaravelSetList::LARAVEL_TYPE_DECLARATIONS,
        ]);
    $config->withSetProviders(LaravelSetProvider::class);
};
PHP;

it('hasRectorConfigWithSets fails when rector.php is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(HasRectorConfigWithSetsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('hasRectorConfigWithSets fails when LaravelBaselineSetList is missing from withSets', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
return static function (RectorConfig $config): void {
    $config->withSets([]);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(HasRectorConfigWithSetsCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('hasRectorConfigWithSets fails when LaravelSetList constants are missing from withSets', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use Limenet\LaravelBaseline\Rector\LaravelBaselineSetList;
return static function (RectorConfig $config): void {
    $config->withSets([LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS]);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(HasRectorConfigWithSetsCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('withSets()')->toContain('LaravelSetList');
});

it('hasRectorConfigWithSets passes when correctly configured', function () use ($validRector): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $validRector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(HasRectorConfigWithSetsCheck::class)->check())->toBe(CheckResult::PASS);
});
