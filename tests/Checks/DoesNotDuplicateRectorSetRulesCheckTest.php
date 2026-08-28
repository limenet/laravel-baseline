<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotDuplicateRectorSetRulesCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

$modern = ['name' => 'tmp', 'require' => ['driftingly/rector-laravel' => '^2.6.1']];

$duplicating = <<<'PHP'
<?php

use Rector\Config\RectorConfig;
use RectorLaravel\Rector\Class_\UseForwardsCallsTraitRector;
use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;
use RectorLaravel\Rector\StaticCall\MinutesToSecondsInCacheRector;
use RectorLaravel\Set\LaravelSetList;

return RectorConfig::configure()
    ->withRules([
        AddGenericReturnTypeToRelationsRector::class,
        MinutesToSecondsInCacheRector::class,
        UseForwardsCallsTraitRector::class,
    ])
    ->withSets([LaravelSetList::LARAVEL_TYPE_DECLARATIONS]);
PHP;

it('doesNotDuplicateRectorSetRules implements FixableInterface', function (): void {
    expect(makeCheck(DoesNotDuplicateRectorSetRulesCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('doesNotDuplicateRectorSetRules passes when withRules does not list the set rule', function () use ($modern): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php

use Rector\Config\RectorConfig;
use RectorLaravel\Rector\StaticCall\MinutesToSecondsInCacheRector;

return RectorConfig::configure()->withRules([MinutesToSecondsInCacheRector::class]);
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($modern)]);

    expect(makeCheck(DoesNotDuplicateRectorSetRulesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotDuplicateRectorSetRules fails while AddGenericReturnTypeToRelationsRector is in withRules', function () use ($duplicating, $modern): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $duplicating, 'composer.json' => json_encode($modern)]);

    $check = makeCheck(DoesNotDuplicateRectorSetRulesCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('AddGenericReturnTypeToRelationsRector')
        ->toContain('LARAVEL_TYPE_DECLARATIONS');
});

it('doesNotDuplicateRectorSetRules fix removes the entry, keeping the others', function () use ($duplicating, $modern): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $duplicating, 'composer.json' => json_encode($modern)]);

    $check = makeCheck(DoesNotDuplicateRectorSetRulesCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $written = (string) file_get_contents(base_path('rector.php'));
    expect($written)->not->toContain('AddGenericReturnTypeToRelationsRector::class')
        ->toContain('MinutesToSecondsInCacheRector::class')
        ->toContain('UseForwardsCallsTraitRector::class')
        ->toContain('use RectorLaravel\Rector\StaticCall\MinutesToSecondsInCacheRector;')
        ->toContain('LaravelSetList::LARAVEL_TYPE_DECLARATIONS');
    // The orphaned import is the linter's job, not this check's.
    expect($written)->toContain('use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;');
});

it('doesNotDuplicateRectorSetRules fix drops an emptied withRules call', function () use ($modern): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php

use Rector\Config\RectorConfig;
use RectorLaravel\Rector\ClassMethod\AddGenericReturnTypeToRelationsRector;

return RectorConfig::configure()
    ->withRules([AddGenericReturnTypeToRelationsRector::class])
    ->withPhpSets();
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($modern)]);

    $check = makeCheck(DoesNotDuplicateRectorSetRulesCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $written = (string) file_get_contents(base_path('rector.php'));
    expect($written)->not->toContain('withRules')
        ->not->toContain('AddGenericReturnTypeToRelationsRector::class')
        ->toContain('withPhpSets()');
});

it('doesNotDuplicateRectorSetRules fix is idempotent', function () use ($duplicating, $modern): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $duplicating, 'composer.json' => json_encode($modern)]);

    $check = makeCheck(DoesNotDuplicateRectorSetRulesCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    $first = (string) file_get_contents(base_path('rector.php'));

    expect($check->fix())->toBe(CheckResult::PASS);
    expect((string) file_get_contents(base_path('rector.php')))->toBe($first);
});
