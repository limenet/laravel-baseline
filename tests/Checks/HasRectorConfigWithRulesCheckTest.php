<?php

use Limenet\LaravelBaseline\Checks\Checks\HasRectorConfigWithRulesCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

$validRector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Rector\StaticCall\MinutesToSecondsInCacheRector;
use RectorLaravel\Rector\Class_\UseForwardsCallsTraitRector;
use Limenet\LaravelBaseline\Rector\LaravelBaselineSetList;

return static function (RectorConfig $config): void {
    $config
        ->withPaths([__DIR__.'/app', __DIR__.'/database', __DIR__.'/routes', __DIR__.'/tests'])
        ->withComposerBased(phpunit: true, symfony: true, laravel: true)
        ->withPreparedSets(deadCode: true, codeQuality: true, codingStyle: true, typeDeclarations: true, privatization: true, instanceOf: true, earlyReturn: true)
        ->withPhpSets()
        ->withAttributesSets()
        ->withImportNames(importShortClasses: false)
        ->withRules([
            MinutesToSecondsInCacheRector::class,
            UseForwardsCallsTraitRector::class,
        ])
        ->withSets([LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS]);
};
PHP;

it('hasRectorConfigWithRules fails when rector.php is missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(HasRectorConfigWithRulesCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('hasRectorConfigWithRules fails when UseForwardsCallsTraitRector is missing', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
return static function (RectorConfig $config): void {
    $config->withComposerBased(phpunit: true, symfony: true, laravel: true)->withPreparedSets(deadCode: true)->withPhpSets()->withAttributesSets()->withImportNames(importShortClasses: false)->withRules([]);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(HasRectorConfigWithRulesCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('withRules()')->toContain('UseForwardsCallsTraitRector');
});

it('hasRectorConfigWithRules fails when MinutesToSecondsInCacheRector is missing', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Rector\Class_\UseForwardsCallsTraitRector;
return static function (RectorConfig $config): void {
    $config->withRules([UseForwardsCallsTraitRector::class]);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(HasRectorConfigWithRulesCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('withRules()')->toContain('MinutesToSecondsInCacheRector');
});

it('hasRectorConfigWithRules passes when correctly configured', function () use ($validRector): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $validRector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(HasRectorConfigWithRulesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('hasRectorConfigWithRules no longer mandates AddGenericReturnTypeToRelationsRector', function (): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use RectorLaravel\Rector\StaticCall\MinutesToSecondsInCacheRector;
use RectorLaravel\Rector\Class_\UseForwardsCallsTraitRector;
return static function (RectorConfig $config): void {
    $config->withRules([MinutesToSecondsInCacheRector::class, UseForwardsCallsTraitRector::class]);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(HasRectorConfigWithRulesCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('hasRectorConfigWithRules fix does not write AddGenericReturnTypeToRelationsRector', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(HasRectorConfigWithRulesCheck::class)->fix())->toBe(CheckResult::PASS);

    $written = (string) file_get_contents(base_path('rector.php'));
    expect($written)->toContain('MinutesToSecondsInCacheRector')
        ->toContain('UseForwardsCallsTraitRector')
        ->not->toContain('AddGenericReturnTypeToRelationsRector');
});
