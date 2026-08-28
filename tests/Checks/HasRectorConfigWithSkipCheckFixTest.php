<?php

use Limenet\LaravelBaseline\Checks\Checks\HasRectorConfigWithSkipCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

$composer = [
    'name' => 'tmp',
    'require' => ['laravel/framework' => '^13.0', 'driftingly/rector-laravel' => '^2.6.1'],
];

it('hasRectorConfigWithSkip implements FixableInterface', function (): void {
    expect(makeCheck(HasRectorConfigWithSkipCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('hasRectorConfigWithSkip fix writes an imported withSkip when rector.php has none', function () use ($composer): void {
    bindFakeComposer(['driftingly/rector-laravel' => true]);
    $rector = "<?php\n\nuse Rector\\Config\\RectorConfig;\n\nreturn RectorConfig::configure();\n";
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($composer)]);

    $check = makeCheck(HasRectorConfigWithSkipCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $written = (string) file_get_contents(base_path('rector.php'));
    expect($written)->toContain('withSkip(')
        ->toContain('StringToClassConstantRector::class')
        ->toContain('AddGenericBuilderToScopesRector::class')
        ->toContain('TablePropertyToTableAttributeRector::class')
        ->toContain('use Rector\Transform\Rector\String_\StringToClassConstantRector;')
        ->toContain('use RectorLaravel\Rector\ClassMethod\AddGenericBuilderToScopesRector;')
        ->toContain('use RectorLaravel\Rector\StaticCall\CarbonToDateFacadeRector;');
});

it('hasRectorConfigWithSkip fix merges into an existing withSkip instead of giving up', function () use ($composer): void {
    bindFakeComposer(['driftingly/rector-laravel' => true]);
    $rector = <<<'PHP'
<?php

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSkip([
        CarbonToDateFacadeRector::class,
        AppToResolveRector::class,
        RedirectBackToBackHelperRector::class,
        RedirectRouteToToRouteHelperRector::class,
        NowFuncWithStartOfDayMethodCallToTodayFuncRector::class,
        EloquentOrderByToLatestOrOldestRector::class,
    ]);
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($composer)]);

    $check = makeCheck(HasRectorConfigWithSkipCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $written = (string) file_get_contents(base_path('rector.php'));
    expect($written)->toContain('StringToClassConstantRector::class')
        ->toContain('AddGenericBuilderToScopesRector::class')
        ->toContain('TablePropertyToTableAttributeRector::class')
        // the entries that were already there survive, exactly once
        ->toContain('CarbonToDateFacadeRector::class');
    expect(substr_count($written, 'CarbonToDateFacadeRector::class'))->toBe(1);
    expect(substr_count($written, 'withSkip('))->toBe(1);
});

it('hasRectorConfigWithSkip fix is idempotent', function () use ($composer): void {
    bindFakeComposer(['driftingly/rector-laravel' => true]);
    $rector = "<?php\n\nuse Rector\\Config\\RectorConfig;\n\nreturn RectorConfig::configure();\n";
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($composer)]);

    $check = makeCheck(HasRectorConfigWithSkipCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    $first = (string) file_get_contents(base_path('rector.php'));

    expect($check->fix())->toBe(CheckResult::PASS);
    expect((string) file_get_contents(base_path('rector.php')))->toBe($first);
});

it('hasRectorConfigWithSkip fix imports entries an older fix wrote bare', function () use ($composer): void {
    bindFakeComposer(['driftingly/rector-laravel' => true]);
    // What the pre-2.10 fix produced: short class names and no use-statements,
    // which Rector rejects with "These rules from skip() do not exist".
    $rector = <<<'PHP'
<?php

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSkip([
        CarbonToDateFacadeRector::class,
        AppToResolveRector::class,
        RedirectBackToBackHelperRector::class,
        RedirectRouteToToRouteHelperRector::class,
        NowFuncWithStartOfDayMethodCallToTodayFuncRector::class,
        EloquentOrderByToLatestOrOldestRector::class,
    ]);
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($composer)]);

    expect(makeCheck(HasRectorConfigWithSkipCheck::class)->fix())->toBe(CheckResult::PASS);

    $written = (string) file_get_contents(base_path('rector.php'));
    expect($written)->toContain('use RectorLaravel\Rector\StaticCall\CarbonToDateFacadeRector;')
        ->toContain('use RectorLaravel\Rector\FuncCall\AppToResolveRector;')
        ->toContain('use RectorLaravel\Rector\MethodCall\RedirectBackToBackHelperRector;')
        ->toContain('use RectorLaravel\Rector\MethodCall\RedirectRouteToToRouteHelperRector;')
        ->toContain('use RectorLaravel\Rector\FuncCall\NowFuncWithStartOfDayMethodCallToTodayFuncRector;')
        ->toContain('use RectorLaravel\Rector\MethodCall\EloquentOrderByToLatestOrOldestRector;');
});

it('hasRectorConfigWithSkip fix leaves a fully-qualified entry unimported', function () use ($composer): void {
    bindFakeComposer(['driftingly/rector-laravel' => true]);
    $rector = <<<'PHP'
<?php

use Rector\Config\RectorConfig;

return RectorConfig::configure()
    ->withSkip([
        \RectorLaravel\Rector\StaticCall\CarbonToDateFacadeRector::class,
    ]);
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($composer)]);

    expect(makeCheck(HasRectorConfigWithSkipCheck::class)->fix())->toBe(CheckResult::PASS);

    $written = (string) file_get_contents(base_path('rector.php'));
    expect($written)->not->toContain('use RectorLaravel\Rector\StaticCall\CarbonToDateFacadeRector;');
    expect(substr_count($written, 'CarbonToDateFacadeRector'))->toBe(1);
});
