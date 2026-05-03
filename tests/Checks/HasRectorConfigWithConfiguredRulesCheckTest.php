<?php

use Limenet\LaravelBaseline\Checks\Checks\HasRectorConfigWithConfiguredRulesCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

$validRector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;
use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;
use Limenet\LaravelBaseline\Rector\LaravelBaselineSetList;
use RectorLaravel\Rector\Route\RouteActionCallableRector;
use RectorLaravel\Rector\MethodCall\WhereToWhereLikeRector;

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
        ->withConfiguredRule(RouteActionCallableRector::class, [RouteActionCallableRector::NAMESPACE => 'App\Http\Controllers'])
        ->withConfiguredRule(WhereToWhereLikeRector::class, [WhereToWhereLikeRector::USING_POSTGRES_DRIVER => false]);
    $config->withSetProviders(LaravelSetProvider::class);
};
PHP;

it('hasRectorConfigWithConfiguredRules fails when rector.php is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(HasRectorConfigWithConfiguredRulesCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('hasRectorConfigWithConfiguredRules fails when withConfiguredRule calls are missing', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
return static function (RectorConfig $config): void {
    $config->withPhpSets();
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(HasRectorConfigWithConfiguredRulesCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('withConfiguredRule()')->toContain('RouteActionCallableRector');
});

it('hasRectorConfigWithConfiguredRules fails when only one rule is configured', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Rector\Route\RouteActionCallableRector;
return static function (RectorConfig $config): void {
    $config->withConfiguredRule(RouteActionCallableRector::class, [RouteActionCallableRector::NAMESPACE => 'App\Http\Controllers']);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(HasRectorConfigWithConfiguredRulesCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('withConfiguredRule()')->toContain('WhereToWhereLikeRector');
});

it('hasRectorConfigWithConfiguredRules passes when both rules are configured', function () use ($validRector): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $validRector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(HasRectorConfigWithConfiguredRulesCheck::class)->check())->toBe(CheckResult::PASS);
});
