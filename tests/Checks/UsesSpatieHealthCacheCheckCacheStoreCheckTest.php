<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieHealthCacheCheckCacheStoreCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

$validAppServiceProvider = <<<'PHP'
<?php
Health::checks([
    CacheCheck::new()->driver('health-checks'),
]);
PHP;

$validCache = <<<'PHP'
<?php
return ['stores' => ['health-checks' => ['driver' => 'file', 'path' => storage_path('framework/cache/health-checks')]]];
PHP;

$validGitignore = "*\n!.gitignore\n";

it('usesSpatieHealthCacheCheckCacheStore warns when package is not installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesSpatieHealthCacheCheckCacheStoreCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesSpatieHealthCacheCheckCacheStore fails when AppServiceProvider is missing', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthCacheCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("CacheCheck must use the dedicated cache store: change CacheCheck::new() to CacheCheck::new()->driver('health-checks') in AppServiceProvider");
});

it('usesSpatieHealthCacheCheckCacheStore fails when CacheCheck has no driver call', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                CacheCheck::new(),
            ]);
            PHP,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthCacheCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("CacheCheck must use the dedicated cache store: change CacheCheck::new() to CacheCheck::new()->driver('health-checks') in AppServiceProvider");
});

it('usesSpatieHealthCacheCheckCacheStore fails when driver uses the wrong store name', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                CacheCheck::new()->driver('default'),
            ]);
            PHP,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthCacheCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("CacheCheck must use the dedicated cache store: change CacheCheck::new() to CacheCheck::new()->driver('health-checks') in AppServiceProvider");
});

it('usesSpatieHealthCacheCheckCacheStore fails when config/cache.php is missing the health-checks store', function () use ($validAppServiceProvider): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => '<?php return ["stores" => ["redis" => ["driver" => "redis"]]];',
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthCacheCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing health-checks cache store: add a 'health-checks' entry with driver 'file' under 'stores' in config/cache.php");
});

it('usesSpatieHealthCacheCheckCacheStore fails when health-checks store uses the wrong driver', function () use ($validAppServiceProvider): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => '<?php return ["stores" => ["health-checks" => ["driver" => "redis"]]];',
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthCacheCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing health-checks cache store: add a 'health-checks' entry with driver 'file' under 'stores' in config/cache.php");
});

it('usesSpatieHealthCacheCheckCacheStore fails when health-checks store has no path', function () use ($validAppServiceProvider): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => '<?php return ["stores" => ["health-checks" => ["driver" => "file"]]];',
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthCacheCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Incorrect path in health-checks cache store in config/cache.php: set 'path' to storage_path('...')");
});

it('usesSpatieHealthCacheCheckCacheStore fails when health-checks store path is not a storage_path call', function () use ($validAppServiceProvider): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => '<?php return ["stores" => ["health-checks" => ["driver" => "file", "path" => "/absolute/path"]]];',
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthCacheCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Incorrect path in health-checks cache store in config/cache.php: set 'path' to storage_path('...')");
});

it('usesSpatieHealthCacheCheckCacheStore fails when .gitignore is missing', function () use ($validAppServiceProvider, $validCache): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => $validCache,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthCacheCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing or invalid .gitignore at the health-checks cache store path: create the file with '*' on the first line and '!.gitignore' on the second");
});

it('usesSpatieHealthCacheCheckCacheStore fails when .gitignore has wrong content', function () use ($validAppServiceProvider, $validCache): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => $validCache,
        'storage/framework/cache/health-checks/.gitignore' => "*.log\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthCacheCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing or invalid .gitignore at the health-checks cache store path: create the file with '*' on the first line and '!.gitignore' on the second");
});

it('usesSpatieHealthCacheCheckCacheStore passes when both are correctly configured', function () use ($validAppServiceProvider, $validCache, $validGitignore): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => $validCache,
        'storage/framework/cache/health-checks/.gitignore' => $validGitignore,
    ]);

    expect(makeCheck(UsesSpatieHealthCacheCheckCacheStoreCheck::class)->check())->toBe(CheckResult::PASS);
});

it('usesSpatieHealthCacheCheckCacheStore passes when CacheCheck has additional chained methods', function () use ($validCache, $validGitignore): void {
    bindFakeComposer(['spatie/laravel-health' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                CacheCheck::new()->driver('health-checks')->name('cache'),
            ]);
            PHP,
        'config/cache.php' => $validCache,
        'storage/framework/cache/health-checks/.gitignore' => $validGitignore,
    ]);

    expect(makeCheck(UsesSpatieHealthCacheCheckCacheStoreCheck::class)->check())->toBe(CheckResult::PASS);
});
