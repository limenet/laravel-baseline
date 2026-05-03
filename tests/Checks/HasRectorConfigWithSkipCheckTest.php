<?php

use Limenet\LaravelBaseline\Checks\Checks\HasRectorConfigWithSkipCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

$validRectorBase = <<<'PHP'
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
        ->withSets([LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS])
        ->withSkip([
            CarbonToDateFacadeRector::class,
            AppToResolveRector::class,
            RedirectBackToBackHelperRector::class,
            RedirectRouteToToRouteHelperRector::class,
            NowFuncWithStartOfDayMethodCallToTodayFuncRector::class,
            EloquentOrderByToLatestOrOldestRector::class,
        ]);
    $config->withSetProviders(LaravelSetProvider::class);
};
PHP;

$validRectorLaravel13 = <<<'PHP'
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
        ->withSkip([
            CarbonToDateFacadeRector::class,
            AppToResolveRector::class,
            RedirectBackToBackHelperRector::class,
            RedirectRouteToToRouteHelperRector::class,
            NowFuncWithStartOfDayMethodCallToTodayFuncRector::class,
            EloquentOrderByToLatestOrOldestRector::class,
            TablePropertyToTableAttributeRector::class,
        ]);
    $config->withSetProviders(LaravelSetProvider::class);
};
PHP;

$validRectorWithServerPhp = <<<'PHP'
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
        ->withSets([LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS])
        ->withSkip([
            CarbonToDateFacadeRector::class,
            AppToResolveRector::class,
            RedirectBackToBackHelperRector::class,
            RedirectRouteToToRouteHelperRector::class,
            NowFuncWithStartOfDayMethodCallToTodayFuncRector::class,
            EloquentOrderByToLatestOrOldestRector::class,
            ServerVariableToRequestFacadeRector::class => ['server.php'],
        ]);
    $config->withSetProviders(LaravelSetProvider::class);
};
PHP;

$laravel13Composer = ['name' => 'tmp', 'require' => ['laravel/framework' => '^13.0']];
$laravel12Composer = ['name' => 'tmp', 'require' => ['laravel/framework' => '^12.0']];

it('hasRectorConfigWithSkip fails when rector.php is missing', function () use ($laravel12Composer): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode($laravel12Composer)]);

    expect(makeCheck(HasRectorConfigWithSkipCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('hasRectorConfigWithSkip fails when required rules are missing from withSkip', function () use ($laravel12Composer): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
return static function (RectorConfig $config): void {
    $config->withSkip([]);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($laravel12Composer)]);

    $check = makeCheck(HasRectorConfigWithSkipCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('withSkip()')->toContain('CarbonToDateFacadeRector');
});

it('hasRectorConfigWithSkip fails when TablePropertyToTableAttributeRector is missing on Laravel 13', function () use ($validRectorBase, $laravel13Composer): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $validRectorBase, 'composer.json' => json_encode($laravel13Composer)]);

    $check = makeCheck(HasRectorConfigWithSkipCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('withSkip()')->toContain('TablePropertyToTableAttributeRector');
});

it('hasRectorConfigWithSkip fails when ServerVariableToRequestFacadeRector is missing with server.php', function () use ($validRectorBase, $laravel12Composer): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $validRectorBase, 'composer.json' => json_encode($laravel12Composer), 'server.php' => '']);

    $check = makeCheck(HasRectorConfigWithSkipCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('withSkip()')->toContain('ServerVariableToRequestFacadeRector');
});

it('hasRectorConfigWithSkip passes on Laravel 12 without TablePropertyToTableAttributeRector', function () use ($validRectorBase, $laravel12Composer): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $validRectorBase, 'composer.json' => json_encode($laravel12Composer)]);

    expect(makeCheck(HasRectorConfigWithSkipCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hasRectorConfigWithSkip passes on Laravel 13 with TablePropertyToTableAttributeRector', function () use ($validRectorLaravel13, $laravel13Composer): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $validRectorLaravel13, 'composer.json' => json_encode($laravel13Composer)]);

    expect(makeCheck(HasRectorConfigWithSkipCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hasRectorConfigWithSkip passes when server.php exists and ServerVariableToRequestFacadeRector is skipped', function () use ($validRectorWithServerPhp, $laravel12Composer): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $validRectorWithServerPhp, 'composer.json' => json_encode($laravel12Composer), 'server.php' => '']);

    expect(makeCheck(HasRectorConfigWithSkipCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hasRectorConfigWithSkip passes without server.php even without ServerVariableToRequestFacadeRector', function () use ($validRectorBase, $laravel12Composer): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $validRectorBase, 'composer.json' => json_encode($laravel12Composer)]);

    expect(makeCheck(HasRectorConfigWithSkipCheck::class)->check())->toBe(CheckResult::PASS);
});
