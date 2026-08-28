<?php

use Limenet\LaravelBaseline\Checks\Checks\DoesNotUseRectorSetProvidersCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

$modern = ['name' => 'tmp', 'require' => ['driftingly/rector-laravel' => '^2.6.1']];

$chained = <<<'PHP'
<?php

use Limenet\LaravelBaseline\Rector\LaravelBaselineSetList;
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;

return RectorConfig::configure()
    ->withComposerBased(phpunit: true, symfony: true, laravel: true)
    ->withSetProviders(LaravelSetProvider::class)
    ->withSets([LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS]);
PHP;

$standalone = <<<'PHP'
<?php

use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;

return static function (RectorConfig $config): void {
    $config->withComposerBased(phpunit: true, symfony: true, laravel: true);
    $config->withSetProviders(LaravelSetProvider::class);
};
PHP;

it('doesNotUseRectorSetProviders implements FixableInterface', function (): void {
    expect(makeCheck(DoesNotUseRectorSetProvidersCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('doesNotUseRectorSetProviders passes when rector.php is missing', function () use ($modern): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode($modern)]);

    expect(makeCheck(DoesNotUseRectorSetProvidersCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotUseRectorSetProviders passes when rector.php never called it', function () use ($modern): void {
    bindFakeComposer([]);
    $rector = "<?php\n\nuse Rector\\Config\\RectorConfig;\n\nreturn RectorConfig::configure()->withPhpSets();\n";
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($modern)]);

    expect(makeCheck(DoesNotUseRectorSetProvidersCheck::class)->check())->toBe(CheckResult::PASS);
});

it('doesNotUseRectorSetProviders fails while the call is still there', function () use ($chained, $modern): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $chained, 'composer.json' => json_encode($modern)]);

    $check = makeCheck(DoesNotUseRectorSetProvidersCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments()[0])->toContain('withSetProviders()')->toContain('LaravelSetProvider');
});

it('doesNotUseRectorSetProviders fix splices the call out of the chain', function () use ($chained, $modern): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $chained, 'composer.json' => json_encode($modern)]);

    $check = makeCheck(DoesNotUseRectorSetProvidersCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $written = (string) file_get_contents(base_path('rector.php'));
    expect($written)->not->toContain('withSetProviders')
        // the rest of the chain survives
        ->toContain('withComposerBased(phpunit: true, symfony: true, laravel: true)')
        ->toContain('LaravelBaselineSetList::REMOVE_DEFAULT_DOCBLOCKS')
        ->toContain('use Rector\Config\RectorConfig;');
});

it('doesNotUseRectorSetProviders leaves the orphaned import to the linter', function () use ($chained, $modern): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $chained, 'composer.json' => json_encode($modern)]);

    expect(makeCheck(DoesNotUseRectorSetProvidersCheck::class)->fix())->toBe(CheckResult::PASS);

    // Pint's no_unused_imports and Rector's dead-code set both strip it on the
    // next pass, so the check does not need to touch use-statements itself.
    expect((string) file_get_contents(base_path('rector.php')))
        ->toContain('use RectorLaravel\Set\LaravelSetProvider;');
});

it('doesNotUseRectorSetProviders fix removes a standalone statement entirely', function () use ($standalone, $modern): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $standalone, 'composer.json' => json_encode($modern)]);

    $check = makeCheck(DoesNotUseRectorSetProvidersCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $written = (string) file_get_contents(base_path('rector.php'));
    expect($written)->not->toContain('withSetProviders')
        ->not->toContain('$config;')
        ->toContain('$config->withComposerBased');
});

it('doesNotUseRectorSetProviders fix is idempotent', function () use ($chained, $modern): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['rector.php' => $chained, 'composer.json' => json_encode($modern)]);

    $check = makeCheck(DoesNotUseRectorSetProvidersCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
    $first = (string) file_get_contents(base_path('rector.php'));

    expect($check->fix())->toBe(CheckResult::PASS);
    expect((string) file_get_contents(base_path('rector.php')))->toBe($first);
});

it('doesNotUseRectorSetProviders keeps a custom set provider', function () use ($modern): void {
    bindFakeComposer([]);
    $rector = <<<'PHP'
<?php

use App\Rector\MySetProvider;
use Rector\Config\RectorConfig;
use RectorLaravel\Set\LaravelSetProvider;

return RectorConfig::configure()
    ->withSetProviders(LaravelSetProvider::class, MySetProvider::class);
PHP;
    $this->withTempBasePath(['rector.php' => $rector, 'composer.json' => json_encode($modern)]);

    $check = makeCheck(DoesNotUseRectorSetProvidersCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $written = (string) file_get_contents(base_path('rector.php'));
    expect($written)->toContain('withSetProviders(MySetProvider::class)')
        ->toContain('use App\Rector\MySetProvider;')
        ->not->toContain('LaravelSetProvider::class');
});
