<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieHealthScheduleCheckCacheStoreCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

$validAppServiceProvider = <<<'PHP'
<?php
Health::checks([
    ScheduleCheck::new()->useCacheStore('health-checks'),
]);
PHP;

$validCache = <<<'PHP'
<?php
return ['stores' => ['health-checks' => ['driver' => 'file']]];
PHP;

it('usesSpatieHealthScheduleCheckCacheStore warns when package is not installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesSpatieHealthScheduleCheckCacheStoreCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesSpatieHealthScheduleCheckCacheStore fails when AppServiceProvider is missing', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthScheduleCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("ScheduleCheck must use the dedicated cache store: change ScheduleCheck::new() to ScheduleCheck::new()->useCacheStore('health-checks') in AppServiceProvider");
});

it('usesSpatieHealthScheduleCheckCacheStore fails when ScheduleCheck has no useCacheStore call', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                ScheduleCheck::new(),
            ]);
            PHP,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthScheduleCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("ScheduleCheck must use the dedicated cache store: change ScheduleCheck::new() to ScheduleCheck::new()->useCacheStore('health-checks') in AppServiceProvider");
});

it('usesSpatieHealthScheduleCheckCacheStore fails when useCacheStore uses the wrong store name', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                ScheduleCheck::new()->useCacheStore('default'),
            ]);
            PHP,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthScheduleCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("ScheduleCheck must use the dedicated cache store: change ScheduleCheck::new() to ScheduleCheck::new()->useCacheStore('health-checks') in AppServiceProvider");
});

it('usesSpatieHealthScheduleCheckCacheStore fails when config/cache.php is missing the health-checks store', function () use ($validAppServiceProvider): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => '<?php return ["stores" => ["redis" => ["driver" => "redis"]]];',
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthScheduleCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing health-checks cache store: add a 'health-checks' entry with driver 'file' under 'stores' in config/cache.php");
});

it('usesSpatieHealthScheduleCheckCacheStore fails when health-checks store uses the wrong driver', function () use ($validAppServiceProvider): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => '<?php return ["stores" => ["health-checks" => ["driver" => "redis"]]];',
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthScheduleCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing health-checks cache store: add a 'health-checks' entry with driver 'file' under 'stores' in config/cache.php");
});

it('usesSpatieHealthScheduleCheckCacheStore passes when both are correctly configured', function () use ($validAppServiceProvider, $validCache): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => $validCache,
    ]);

    expect(makeCheck(UsesSpatieHealthScheduleCheckCacheStoreCheck::class)->check())->toBe(CheckResult::PASS);
});

it('usesSpatieHealthScheduleCheckCacheStore passes when ScheduleCheck has additional chained methods', function () use ($validCache): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                ScheduleCheck::new()->useCacheStore('health-checks')->heartbeatMaxAgeInMinutes(5),
            ]);
            PHP,
        'config/cache.php' => $validCache,
    ]);

    expect(makeCheck(UsesSpatieHealthScheduleCheckCacheStoreCheck::class)->check())->toBe(CheckResult::PASS);
});
