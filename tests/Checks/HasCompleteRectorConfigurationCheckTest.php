<?php

use Limenet\LaravelBaseline\Checks\Checks\HasCompleteRectorConfigurationCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('hasCompleteRectorConfiguration fails when file missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(HasCompleteRectorConfigurationCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('hasCompleteRectorConfiguration passes when configuration is complete on Laravel 12', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;
use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;
use Limenet\LaravelBaseline\Rector\LaravelBaselineSetList;

return static function (RectorConfig $config): void {
    $config
        ->withPaths([
            __DIR__.'/app',
            __DIR__.'/database',
            __DIR__.'/routes',
            __DIR__.'/tests',
        ])
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            earlyReturn: true,
        )
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false)
        ->withRules([
            AddGenericReturnTypeToRelationsRector::class,
        ])
        ->withSets([
            LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS,
        ]);

    $config->withSetProviders(LaravelSetProvider::class);
};
PHP;
    $composer = ['name' => 'tmp', 'require' => ['laravel/framework' => '^12.0']];
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($composer)]);

    expect(makeCheck(HasCompleteRectorConfigurationCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hasCompleteRectorConfiguration passes when configuration is complete on Laravel 13', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;
use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;
use Limenet\LaravelBaseline\Rector\LaravelBaselineSetList;
use RectorLaravel\Rector\Class_\TablePropertyToTableAttributeRector;

return static function (RectorConfig $config): void {
    $config
        ->withPaths([
            __DIR__.'/app',
            __DIR__.'/database',
            __DIR__.'/routes',
            __DIR__.'/tests',
        ])
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            earlyReturn: true,
        )
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false)
        ->withRules([
            AddGenericReturnTypeToRelationsRector::class,
        ])
        ->withSets([
            LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS,
        ])
        ->withSkip([
            TablePropertyToTableAttributeRector::class,
        ]);

    $config->withSetProviders(LaravelSetProvider::class);
};
PHP;
    $composer = ['name' => 'tmp', 'require' => ['laravel/framework' => '^13.0']];
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($composer)]);

    expect(makeCheck(HasCompleteRectorConfigurationCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hasCompleteRectorConfiguration fails without withSkip on Laravel 13', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;
use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;
use Limenet\LaravelBaseline\Rector\LaravelBaselineSetList;

return static function (RectorConfig $config): void {
    $config
        ->withPaths([
            __DIR__.'/app',
            __DIR__.'/database',
            __DIR__.'/routes',
            __DIR__.'/tests',
        ])
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            earlyReturn: true,
        )
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false)
        ->withRules([
            AddGenericReturnTypeToRelationsRector::class,
        ])
        ->withSets([
            LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS,
        ]);

    $config->withSetProviders(LaravelSetProvider::class);
};
PHP;
    $composer = ['name' => 'tmp', 'require' => ['laravel/framework' => '^13.0']];
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($composer)]);

    $check = makeCheck(HasCompleteRectorConfigurationCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('withSkip()')->toContain('TablePropertyToTableAttributeRector');
});

it('hasCompleteRectorConfiguration provides specific error message for missing withComposerBased arguments', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;

return static function (RectorConfig $config): void {
    $config->withComposerBased(phpunit: true, symfony: false, laravel: true);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(HasCompleteRectorConfigurationCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toHaveCount(1);
    expect($comments[0])->toContain('withComposerBased()')
        ->toContain('Expected named arguments: phpunit: true, symfony: true, laravel: true');
});

it('hasCompleteRectorConfiguration provides specific error message for missing withPreparedSets arguments', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;

return static function (RectorConfig $config): void {
    $config
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(deadCode: true, codeQuality: true);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(HasCompleteRectorConfigurationCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toHaveCount(1);
    expect($comments[0])->toContain('withPreparedSets()')
        ->toContain('Expected named arguments')
        ->toContain('deadCode: true')
        ->toContain('typeDeclarations: true');
});

it('hasCompleteRectorConfiguration provides specific error message for missing withPhpSets call', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;

return static function (RectorConfig $config): void {
    $config
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            earlyReturn: true,
        )
        ->withImportNames(importShortClasses: false);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(HasCompleteRectorConfigurationCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toHaveCount(1);
    expect($comments[0])->toContain('Missing call to withPhpSets()');
});

it('hasCompleteRectorConfiguration provides specific error message for missing LaravelSetProvider', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;

return static function (RectorConfig $config): void {
    $config
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            earlyReturn: true,
        )
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(HasCompleteRectorConfigurationCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toHaveCount(1);
    expect($comments[0])->toContain('withSetProviders()')
        ->toContain('LaravelSetProvider');
});

it('hasCompleteRectorConfiguration provides specific error message for missing withRules argument', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;

return static function (RectorConfig $config): void {
    $config
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            earlyReturn: true,
        )
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false)
        ->withSetProviders(LaravelSetProvider::class)
        ->withRules([]);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(HasCompleteRectorConfigurationCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toHaveCount(1);
    expect($comments[0])->toContain('withRules()')
        ->toContain('AddGenericReturnTypeToRelationsRector');
});

it('hasCompleteRectorConfiguration provides specific error message for missing withPaths', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;
use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;
use Limenet\LaravelBaseline\Rector\LaravelBaselineSetList;
use RectorLaravel\Rector\Class_\TablePropertyToTableAttributeRector;

return static function (RectorConfig $config): void {
    $config
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            earlyReturn: true,
        )
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false)
        ->withSetProviders(LaravelSetProvider::class)
        ->withRules([
            AddGenericReturnTypeToRelationsRector::class,
        ])
        ->withSets([
            LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS,
        ])
        ->withSkip([
            TablePropertyToTableAttributeRector::class,
        ]);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(HasCompleteRectorConfigurationCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toHaveCount(1);
    expect($comments[0])->toContain('withPaths()')
        ->toContain('app, database, routes, tests');
});

it('hasCompleteRectorConfiguration provides specific error message for incomplete withPaths', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;
use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;
use Limenet\LaravelBaseline\Rector\LaravelBaselineSetList;
use RectorLaravel\Rector\Class_\TablePropertyToTableAttributeRector;

return static function (RectorConfig $config): void {
    $config
        ->withPaths([
            __DIR__.'/app',
            __DIR__.'/tests',
        ])
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(
            deadCode: true,
            codeQuality: true,
            codingStyle: true,
            typeDeclarations: true,
            privatization: true,
            instanceOf: true,
            earlyReturn: true,
        )
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false)
        ->withSetProviders(LaravelSetProvider::class)
        ->withRules([
            AddGenericReturnTypeToRelationsRector::class,
        ])
        ->withSets([
            LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS,
        ])
        ->withSkip([
            TablePropertyToTableAttributeRector::class,
        ]);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(HasCompleteRectorConfigurationCheck::class);
    $result = $check->check();

    expect($result)->toBe(CheckResult::FAIL);
    $comments = $check->getComments();
    expect($comments)->toHaveCount(1);
    expect($comments[0])->toContain('withPaths()')
        ->toContain('app, database, routes, tests');
});
