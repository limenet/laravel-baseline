<?php

use Limenet\LaravelBaseline\Checks\Checks\HasRectorConfigWithPestSetCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

$applicableComposer = json_encode(['require-dev' => ['pestphp/pest' => '^5.0']]);

$validRector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use Pest\Rector\Set\PestSetList;
use Limenet\LaravelBaseline\Rector\LaravelBaselineSetList;

return static function (RectorConfig $config): void {
    $config->withSets([
        LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS,
        PestSetList::CODING_STYLE,
    ]);
};
PHP;

it('hasRectorConfigWithPestSet warns when pest is below version 5', function (): void {
    bindFakeComposer(['rector/rector' => true]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['require-dev' => ['pestphp/pest' => '^4.0']]),
    ]);

    expect(makeCheck(HasRectorConfigWithPestSetCheck::class)->check())->toBe(CheckResult::WARN);
});

it('hasRectorConfigWithPestSet warns when rector is not installed', function (): void {
    bindFakeComposer(['rector/rector' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['require-dev' => ['pestphp/pest' => '^5.0']])]);

    expect(makeCheck(HasRectorConfigWithPestSetCheck::class)->check())->toBe(CheckResult::WARN);
});

it('hasRectorConfigWithPestSet fails when applicable but rector.php is missing', function () use ($applicableComposer): void {
    bindFakeComposer(['rector/rector' => true]);
    $this->withTempBasePath(['composer.json' => $applicableComposer]);

    expect(makeCheck(HasRectorConfigWithPestSetCheck::class)->check())->toBe(CheckResult::FAIL);
});

it('hasRectorConfigWithPestSet fails when PestSetList::CODING_STYLE is missing from withSets', function () use ($applicableComposer): void {
    bindFakeComposer(['rector/rector' => true]);
    $rector = <<<'PHP'
<?php
use Rector\Config\RectorConfig;
use Limenet\LaravelBaseline\Rector\LaravelBaselineSetList;
return static function (RectorConfig $config): void {
    $config->withSets([LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS]);
};
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => $applicableComposer]);

    [$check, $collector] = makeCheckWithCollector(HasRectorConfigWithPestSetCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain(
        'Rector configuration incomplete: Missing or incorrect call to withSets() in rector.php - Expected array containing: PestSetList::CODING_STYLE',
    );
});

it('hasRectorConfigWithPestSet passes when correctly configured', function () use ($applicableComposer, $validRector): void {
    bindFakeComposer(['rector/rector' => true]);
    $this->withTempBasePath(['rector.php' => $validRector, 'composer.json' => $applicableComposer]);

    expect(makeCheck(HasRectorConfigWithPestSetCheck::class)->check())->toBe(CheckResult::PASS);
});
