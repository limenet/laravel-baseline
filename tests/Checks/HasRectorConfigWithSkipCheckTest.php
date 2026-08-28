<?php

use Limenet\LaravelBaseline\Checks\Checks\HasRectorConfigWithSkipCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

function skipRector(string $extraUses = '', string $extraSkips = ''): string
{
    return <<<PHP
<?php
use Rector\\Config\\RectorConfig;
use Limenet\\LaravelBaseline\\Rector\\LaravelBaselineSetList;
{$extraUses}
return static function (RectorConfig \$config): void {
    \$config
        ->withPaths([__DIR__.'/app', __DIR__.'/database', __DIR__.'/routes', __DIR__.'/tests'])
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPhpSets()
        ->withSets([LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS])
        ->withSkip([
            CarbonToDateFacadeRector::class,
            AppToResolveRector::class,
            RedirectBackToBackHelperRector::class,
            RedirectRouteToToRouteHelperRector::class,
            NowFuncWithStartOfDayMethodCallToTodayFuncRector::class,
            EloquentOrderByToLatestOrOldestRector::class,
            StringToClassConstantRector::class,
            AddGenericBuilderToScopesRector::class,
{$extraSkips}        ]);
};
PHP;
}

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
    expect($check->getComments()[0])->toContain('withSkip()')
        ->toContain('CarbonToDateFacadeRector')
        ->toContain('CarbonToDateFacadeRector::class');
});

it('hasRectorConfigWithSkip requires StringToClassConstantRector', function () use ($laravel12Composer): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
return static function (RectorConfig $config): void {
    $config->withSkip([
        CarbonToDateFacadeRector::class,
        AppToResolveRector::class,
        RedirectBackToBackHelperRector::class,
        RedirectRouteToToRouteHelperRector::class,
        NowFuncWithStartOfDayMethodCallToTodayFuncRector::class,
        EloquentOrderByToLatestOrOldestRector::class,
    ]);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($laravel12Composer)]);

    $check = makeCheck(HasRectorConfigWithSkipCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('StringToClassConstantRector');
});

it('hasRectorConfigWithSkip fails when TablePropertyToTableAttributeRector is missing on Laravel 13', function () use ($laravel13Composer): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => skipRector(), 'composer.json' => json_encode($laravel13Composer)]);

    $check = makeCheck(HasRectorConfigWithSkipCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('withSkip()')->toContain('TablePropertyToTableAttributeRector');
});

it('hasRectorConfigWithSkip fails when AddGenericBuilderToScopesRector is missing', function () use ($laravel12Composer): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
return static function (RectorConfig $config): void {
    $config->withSkip([
        CarbonToDateFacadeRector::class,
        AppToResolveRector::class,
        RedirectBackToBackHelperRector::class,
        RedirectRouteToToRouteHelperRector::class,
        NowFuncWithStartOfDayMethodCallToTodayFuncRector::class,
        EloquentOrderByToLatestOrOldestRector::class,
        StringToClassConstantRector::class,
    ]);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($laravel12Composer)]);

    $check = makeCheck(HasRectorConfigWithSkipCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('AddGenericBuilderToScopesRector');
});

it('hasRectorConfigWithSkip fails when ServerVariableToRequestFacadeRector is missing with server.php', function () use ($laravel12Composer): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        'rector.php' => skipRector(),
        'composer.json' => json_encode($laravel12Composer),
        'server.php' => '',
    ]);

    $check = makeCheck(HasRectorConfigWithSkipCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('withSkip()')->toContain('ServerVariableToRequestFacadeRector');
});

it('hasRectorConfigWithSkip passes on Laravel 12 without TablePropertyToTableAttributeRector', function () use ($laravel12Composer): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => skipRector(), 'composer.json' => json_encode($laravel12Composer)]);

    expect(makeCheck(HasRectorConfigWithSkipCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hasRectorConfigWithSkip passes on Laravel 13 with TablePropertyToTableAttributeRector', function () use ($laravel13Composer): void {
    bindFakeComposer([]);
    $rector = skipRector(
        "use RectorLaravel\\Rector\\Class_\\TablePropertyToTableAttributeRector;\n",
        "            TablePropertyToTableAttributeRector::class,\n",
    );
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($laravel13Composer)]);

    expect(makeCheck(HasRectorConfigWithSkipCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hasRectorConfigWithSkip passes when server.php exists and ServerVariableToRequestFacadeRector is skipped', function () use ($laravel12Composer): void {
    bindFakeComposer([]);
    $rector = skipRector('', "            ServerVariableToRequestFacadeRector::class => ['server.php'],\n");
    $this->withTempBasePath([
        'rector.php' => $rector,
        'composer.json' => json_encode($laravel12Composer),
        'server.php' => '',
    ]);

    expect(makeCheck(HasRectorConfigWithSkipCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hasRectorConfigWithSkip passes without server.php even without ServerVariableToRequestFacadeRector', function () use ($laravel12Composer): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => skipRector(), 'composer.json' => json_encode($laravel12Composer)]);

    expect(makeCheck(HasRectorConfigWithSkipCheck::class)->check())->toBe(CheckResult::PASS);
});
