<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieHealthQueueCheckCacheStoreCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

$validAppServiceProvider = <<<'PHP'
<?php
Health::checks([
    QueueCheck::new()->useCacheStore('health-checks'),
]);
PHP;

$validCache = <<<'PHP'
<?php
return ['stores' => ['health-checks' => ['driver' => 'redis']]];
PHP;

it('usesSpatieHealthQueueCheckCacheStore warns when package is not installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesSpatieHealthQueueCheckCacheStoreCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesSpatieHealthQueueCheckCacheStore fails when AppServiceProvider is missing', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthQueueCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("QueueCheck must use the dedicated cache store: change QueueCheck::new() to QueueCheck::new()->useCacheStore('health-checks') in AppServiceProvider");
});

it('usesSpatieHealthQueueCheckCacheStore fails when QueueCheck has no useCacheStore call', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                QueueCheck::new(),
            ]);
            PHP,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthQueueCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("QueueCheck must use the dedicated cache store: change QueueCheck::new() to QueueCheck::new()->useCacheStore('health-checks') in AppServiceProvider");
});

it('usesSpatieHealthQueueCheckCacheStore fails when useCacheStore uses the wrong store name', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                QueueCheck::new()->useCacheStore('default'),
            ]);
            PHP,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthQueueCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("QueueCheck must use the dedicated cache store: change QueueCheck::new() to QueueCheck::new()->useCacheStore('health-checks') in AppServiceProvider");
});

it('usesSpatieHealthQueueCheckCacheStore fails when config/cache.php is missing the health-checks store', function () use ($validAppServiceProvider): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => '<?php return ["stores" => ["redis" => ["driver" => "redis"]]];',
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthQueueCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing health-checks cache store: add a 'health-checks' entry under 'stores' in config/cache.php");
});

it('usesSpatieHealthQueueCheckCacheStore passes when both are correctly configured', function () use ($validAppServiceProvider, $validCache): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => $validCache,
    ]);

    expect(makeCheck(UsesSpatieHealthQueueCheckCacheStoreCheck::class)->check())->toBe(CheckResult::PASS);
});

it('usesSpatieHealthQueueCheckCacheStore passes when QueueCheck has additional chained methods', function () use ($validCache): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                QueueCheck::new()->useCacheStore('health-checks')->onQueue(['default', 'notifications']),
            ]);
            PHP,
        'config/cache.php' => $validCache,
    ]);

    expect(makeCheck(UsesSpatieHealthQueueCheckCacheStoreCheck::class)->check())->toBe(CheckResult::PASS);
});
