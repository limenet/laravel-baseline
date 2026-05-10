<?php

use Limenet\LaravelBaseline\Checks\Checks\ModelShouldBeStrictCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('modelShouldBeStrict fails when AppServiceProvider does not exist', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(ModelShouldBeStrictCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing Model::shouldBeStrict() call in AppServiceProvider');
});

it('modelShouldBeStrict fails when Model::shouldBeStrict() is not called', function (): void {
    bindFakeComposer([]);
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => '<?php class AppServiceProvider {}',
    ]);

    [$check, $collector] = makeCheckWithCollector(ModelShouldBeStrictCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing Model::shouldBeStrict() call in AppServiceProvider');
});

it('modelShouldBeStrict fails when called with false', function (): void {
    bindFakeComposer([]);

    $provider = <<<'PHP'
<?php
Model::shouldBeStrict(false);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    [$check, $collector] = makeCheckWithCollector(ModelShouldBeStrictCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Do not pass false to Model::shouldBeStrict(); use true, no argument, or a dynamic expression');
});

it('modelShouldBeStrict passes when called with no argument', function (): void {
    bindFakeComposer([]);

    $provider = <<<'PHP'
<?php
Model::shouldBeStrict();
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    expect(makeCheck(ModelShouldBeStrictCheck::class)->check())->toBe(CheckResult::PASS);
});

it('modelShouldBeStrict passes when called with true', function (): void {
    bindFakeComposer([]);

    $provider = <<<'PHP'
<?php
Model::shouldBeStrict(true);
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    expect(makeCheck(ModelShouldBeStrictCheck::class)->check())->toBe(CheckResult::PASS);
});

it('modelShouldBeStrict passes when called with a dynamic expression', function (): void {
    bindFakeComposer([]);

    $provider = <<<'PHP'
<?php
Model::shouldBeStrict(!app()->isProduction());
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $provider,
    ]);

    expect(makeCheck(ModelShouldBeStrictCheck::class)->check())->toBe(CheckResult::PASS);
});
