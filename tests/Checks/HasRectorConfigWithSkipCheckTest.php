<?php

use Limenet\LaravelBaseline\Checks\Checks\HasRectorConfigWithSkipCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

$validRector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;
use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;
use Limenet\LaravelBaseline\Rector\LaravelBaselineSetList;
use RectorLaravel\Rector\Class_\TablePropertyToTableAttributeRector;

return static function (RectorConfig $config): void {
    $config
        ->withPaths([__DIR__.'/app', __DIR__.'/database', __DIR__.'/routes', __DIR__.'/tests'])
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(deadCode: true, codeQuality: true, codingStyle: true, typeDeclarations: true, privatization: true, instanceOf: true, earlyReturn: true)
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false)
        ->withRules([AddGenericReturnTypeToRelationsRector::class])
        ->withSets([LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS])
        ->withSkip([TablePropertyToTableAttributeRector::class]);
    $config->withSetProviders(LaravelSetProvider::class);
};
PHP;

$laravel13Composer = ['name' => 'tmp', 'require' => ['laravel/framework' => '^13.0']];
$laravel12Composer = ['name' => 'tmp', 'require' => ['laravel/framework' => '^12.0']];

it('hasRectorConfigWithSkip warns when not on Laravel 13', function () use ($validRector, $laravel12Composer): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $validRector, 'composer.json' => json_encode($laravel12Composer)]);

    expect(makeCheck(HasRectorConfigWithSkipCheck::class)->check())->toBe(CheckResult::WARN);
});

it('hasRectorConfigWithSkip fails when rector.php is missing on Laravel 13', function () use ($laravel13Composer): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode($laravel13Composer)]);

    expect(makeCheck(HasRectorConfigWithSkipCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('hasRectorConfigWithSkip fails when TablePropertyToTableAttributeRector is not skipped on Laravel 13', function () use ($laravel13Composer): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
return static function (RectorConfig $config): void {
    $config->withPhpSets();
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($laravel13Composer)]);

    $check = makeCheck(HasRectorConfigWithSkipCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('withSkip()')->toContain('TablePropertyToTableAttributeRector');
});

it('hasRectorConfigWithSkip passes when correctly configured on Laravel 13', function () use ($validRector, $laravel13Composer): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $validRector, 'composer.json' => json_encode($laravel13Composer)]);

    expect(makeCheck(HasRectorConfigWithSkipCheck::class)->check())->toBe(CheckResult::PASS);
});
