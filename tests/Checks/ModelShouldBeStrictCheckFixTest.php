<?php

use Limenet\LaravelBaseline\Checks\Checks\ModelShouldBeStrictCheck;
use Limenet\LaravelBaseline\Checks\FixableInterface;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('modelShouldBeStrict implements FixableInterface', function (): void {
    expect(makeCheck(ModelShouldBeStrictCheck::class))->toBeInstanceOf(FixableInterface::class);
});

it('modelShouldBeStrict fix returns fail when AppServiceProvider missing', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([]);

    $check = makeCheck(ModelShouldBeStrictCheck::class);
    expect($check->fix())->toBe(CheckResult::FAIL);
});

it('modelShouldBeStrict fix adds call to boot()', function (): void {
    bindFakeComposer([]);
    $provider = <<<'PHP'
<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
    }
}
PHP;
    $this->withTempBasePath(['app/Providers/AppServiceProvider.php' => $provider]);

    $check = makeCheck(ModelShouldBeStrictCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);

    $content = file_get_contents(base_path('app/Providers/AppServiceProvider.php'));
    expect($content)->toContain('shouldBeStrict');
});

it('modelShouldBeStrict fix adds import inside namespace before class', function (): void {
    bindFakeComposer([]);
    $provider = <<<'PHP'
<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
    }
}
PHP;
    $this->withTempBasePath(['app/Providers/AppServiceProvider.php' => $provider]);

    makeCheck(ModelShouldBeStrictCheck::class)->fix();

    $content = file_get_contents(base_path('app/Providers/AppServiceProvider.php'));
    $importPos = strpos($content, 'use Illuminate\\Database\\Eloquent\\Model');
    $classPos = strpos($content, 'class AppServiceProvider');

    expect($importPos)->not->toBeFalse()
        ->and($importPos)->toBeLessThan($classPos);
});

it('modelShouldBeStrict fix is idempotent when already correct', function (): void {
    bindFakeComposer([]);
    $provider = <<<'PHP'
<?php
namespace App\Providers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;
class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Model::shouldBeStrict(! app()->isProduction());
    }
}
PHP;
    $this->withTempBasePath(['app/Providers/AppServiceProvider.php' => $provider]);

    $check = makeCheck(ModelShouldBeStrictCheck::class);
    expect($check->fix())->toBe(CheckResult::PASS);
});
