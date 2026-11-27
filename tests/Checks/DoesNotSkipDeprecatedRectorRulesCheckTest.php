<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotSkipDeprecatedRectorRulesCheck;
use Limenet\LaravelBaseline\Checks\Checks\HasRectorConfigWithSkipCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Project\Profile;

$modern = ['name' => 'tmp', 'require' => ['laravel/framework' => '^12.0', 'driftingly/rector-laravel' => '^2.7']];

// What hasRectorConfigWithSkip wrote before Rector 2.8 deprecated the rule.
$written = <<<'PHP'
<?php

use Rector\Config\RectorConfig;
use Rector\Transform\Rector\String_\StringToClassConstantRector;
use RectorLaravel\Rector\ClassMethod\AddGenericBuilderToScopesRector;
use RectorLaravel\Rector\ClassMethod\MigrateToSimplifiedAttributeRector;
use RectorLaravel\Rector\FuncCall\AppToResolveRector;
use RectorLaravel\Rector\FuncCall\NowFuncWithStartOfDayMethodCallToTodayFuncRector;
use RectorLaravel\Rector\MethodCall\EloquentOrderByToLatestOrOldestRector;
use RectorLaravel\Rector\MethodCall\RedirectBackToBackHelperRector;
use RectorLaravel\Rector\MethodCall\RedirectRouteToToRouteHelperRector;
use RectorLaravel\Rector\StaticCall\CarbonToDateFacadeRector;

return RectorConfig::configure()
    ->withPhpSets()
    ->withSkip([
        CarbonToDateFacadeRector::class,
        AppToResolveRector::class,
        RedirectBackToBackHelperRector::class,
        RedirectRouteToToRouteHelperRector::class,
        NowFuncWithStartOfDayMethodCallToTodayFuncRector::class,
        EloquentOrderByToLatestOrOldestRector::class,
        StringToClassConstantRector::class,
        AddGenericBuilderToScopesRector::class,
        MigrateToSimplifiedAttributeRector::class,
    ]);
PHP;

it('doesNotSkipDeprecatedRectorRules implements FixableInterface', function (): void {
    expect(makeCheck(DoesNotSkipDeprecatedRectorRulesCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('doesNotSkipDeprecatedRectorRules passes when rector.php is missing', function () use ($modern): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode($modern)]);

    expect(makeCheck(DoesNotSkipDeprecatedRectorRulesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotSkipDeprecatedRectorRules passes when withSkip does not name a deprecated rule', function () use ($modern): void {
    bindFakeComposer([]);
    $rector = "<?php\n\nuse Rector\\Config\\RectorConfig;\n\nreturn RectorConfig::configure()->withSkip([AppToResolveRector::class]);\n";
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($modern)]);

    expect(makeCheck(DoesNotSkipDeprecatedRectorRulesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotSkipDeprecatedRectorRules fails while StringToClassConstantRector is skipped', function () use ($written, $modern): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $written, 'composer.json' => json_encode($modern)]);

    $check = makeCheck(DoesNotSkipDeprecatedRectorRulesCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('withSkip()')->toContain('StringToClassConstantRector');
});

it('doesNotSkipDeprecatedRectorRules fix removes the entry and keeps the rest', function () use ($written, $modern): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $written, 'composer.json' => json_encode($modern)]);

    expect(makeCheck(DoesNotSkipDeprecatedRectorRulesCheck::class)->fix())->toBe(CheckResult::PASS);

    $result = (string) file_get_contents(base_path('rector.php'));
    expect($result)->not->toContain('StringToClassConstantRector::class')
        ->toContain('AppToResolveRector::class')
        ->toContain('MigrateToSimplifiedAttributeRector::class')
        ->toContain('withPhpSets()');

    // and what is left still satisfies the check that used to require it
    expect(makeCheck(HasRectorConfigWithSkipCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotSkipDeprecatedRectorRules fix removes a fully-qualified entry', function () use ($modern): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSkip([
        \Rector\Transform\Rector\String_\StringToClassConstantRector::class,
        \RectorLaravel\Rector\FuncCall\AppToResolveRector::class,
    ]);
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($modern)]);

    expect(makeCheck(DoesNotSkipDeprecatedRectorRulesCheck::class)->fix())->toBe(CheckResult::PASS);

    expect((string) file_get_contents(base_path('rector.php')))
        ->not->toContain('StringToClassConstantRector')
        ->toContain('AppToResolveRector::class');
});

it('doesNotSkipDeprecatedRectorRules fix drops a withSkip it empties', function () use ($modern): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php

use Rector\Config\RectorConfig;
use Rector\Transform\Rector\String_\StringToClassConstantRector;

return RectorConfig::configure()
    ->withPhpSets()
    ->withSkip([StringToClassConstantRector::class]);
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($modern)]);

    expect(makeCheck(DoesNotSkipDeprecatedRectorRulesCheck::class)->fix())->toBe(CheckResult::PASS);

    expect((string) file_get_contents(base_path('rector.php')))
        ->not->toContain('withSkip')
        ->toContain('withPhpSets()');
});

it('doesNotSkipDeprecatedRectorRules fix is idempotent', function () use ($written, $modern): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $written, 'composer.json' => json_encode($modern)]);

    $check = makeCheck(DoesNotSkipDeprecatedRectorRulesCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    $first = (string) file_get_contents(base_path('rector.php'));

    expect($check->fix())->toBe(CheckResult::PASS);
    expect((string) file_get_contents(base_path('rector.php')))->toBe($first);
});

it('doesNotSkipDeprecatedRectorRules fix removes FlipTypeControlToUseExclusiveTypeRector, plain and keyed by paths', function () use ($modern): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use RectorLaravel\Rector\FuncCall\AppToResolveRector;

return RectorConfig::configure()
    ->withSkip([
        FlipTypeControlToUseExclusiveTypeRector::class,
        AppToResolveRector::class,
    ])
    ->withSkip([
        FlipTypeControlToUseExclusiveTypeRector::class => [__DIR__ . '/app/Legacy'],
    ]);
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($modern)]);

    $check = makeCheck(DoesNotSkipDeprecatedRectorRulesCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('FlipTypeControlToUseExclusiveTypeRector');

    expect($check->fix())->toBe(CheckResult::PASS);

    $result = (string) file_get_contents(base_path('rector.php'));
    expect($result)->not->toContain('FlipTypeControlToUseExclusiveTypeRector::class')
        ->not->toContain('app/Legacy')
        ->toContain('AppToResolveRector::class');
});

it('doesNotSkipDeprecatedRectorRules runs outside Laravel', function (Profile $profile): void {
    $project = makeProject($profile, ['rector.php' => <<<'PHP'
<?php

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withPaths([__DIR__ . '/src'])
    ->withSkip([FlipTypeControlToUseExclusiveTypeRector::class]);
PHP]);

    expect(makeCheck(DoesNotSkipDeprecatedRectorRulesCheck::class, $project)->check())->toBe(CheckResult::FAIL);
    expect(makeCheck(DoesNotSkipDeprecatedRectorRulesCheck::class, $project)->fix())->toBe(CheckResult::PASS);
    expect((string) file_get_contents($project->path('rector.php')))
        ->not->toContain('withSkip')
        ->toContain('withPaths');
})->with([Profile::Php, Profile::WordPress]);
