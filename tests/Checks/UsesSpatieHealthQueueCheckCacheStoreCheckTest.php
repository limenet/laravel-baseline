<?php

use Illuminate\Support\Facades\Schedule;
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
return ['stores' => ['health-checks' => ['driver' => 'file', 'path' => storage_path('framework/cache/health-checks')]]];
PHP;

$validGitignore = "*\n!.gitignore\n";

it('usesSpatieHealthQueueCheckCacheStore warns when package is not installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesSpatieHealthQueueCheckCacheStoreCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesSpatieHealthQueueCheckCacheStore fails when health:queue-check-heartbeat is not scheduled', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthQueueCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Missing schedule: add DispatchQueueCheckJobsCommand::class scheduled everyMinute() in your scheduler');
});

it('usesSpatieHealthQueueCheckCacheStore fails when AppServiceProvider is missing', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);
    Schedule::command('health:queue-check-heartbeat');

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthQueueCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("QueueCheck must use the dedicated cache store: change QueueCheck::new() to QueueCheck::new()->useCacheStore('health-checks') in AppServiceProvider");
});

it('usesSpatieHealthQueueCheckCacheStore fails when QueueCheck has no useCacheStore call', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    Schedule::command('health:queue-check-heartbeat');

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
    Schedule::command('health:queue-check-heartbeat');

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
    Schedule::command('health:queue-check-heartbeat');

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => '<?php return ["stores" => ["redis" => ["driver" => "redis"]]];',
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthQueueCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing health-checks cache store: add a 'health-checks' entry with driver 'file' under 'stores' in config/cache.php");
});

it('usesSpatieHealthQueueCheckCacheStore fails when health-checks store uses the wrong driver', function () use ($validAppServiceProvider): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    Schedule::command('health:queue-check-heartbeat');

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => '<?php return ["stores" => ["health-checks" => ["driver" => "redis"]]];',
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthQueueCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing health-checks cache store: add a 'health-checks' entry with driver 'file' under 'stores' in config/cache.php");
});

it('usesSpatieHealthQueueCheckCacheStore fails when health-checks store has no path', function () use ($validAppServiceProvider): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    Schedule::command('health:queue-check-heartbeat');

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => '<?php return ["stores" => ["health-checks" => ["driver" => "file"]]];',
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthQueueCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Incorrect path in health-checks cache store in config/cache.php: set 'path' to storage_path('...')");
});

it('usesSpatieHealthQueueCheckCacheStore fails when health-checks store path is not a storage_path call', function () use ($validAppServiceProvider): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    Schedule::command('health:queue-check-heartbeat');

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => '<?php return ["stores" => ["health-checks" => ["driver" => "file", "path" => "/absolute/path"]]];',
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthQueueCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Incorrect path in health-checks cache store in config/cache.php: set 'path' to storage_path('...')");
});

it('usesSpatieHealthQueueCheckCacheStore fails when .gitignore is missing', function () use ($validAppServiceProvider, $validCache): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    Schedule::command('health:queue-check-heartbeat');

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => $validCache,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthQueueCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing or invalid .gitignore at the health-checks cache store path: create the file with '*' on the first line and '!.gitignore' on the second");
});

it('usesSpatieHealthQueueCheckCacheStore fails when .gitignore has wrong content', function () use ($validAppServiceProvider, $validCache): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    Schedule::command('health:queue-check-heartbeat');

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => $validCache,
        'storage/framework/cache/health-checks/.gitignore' => "*.log\n",
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthQueueCheckCacheStoreCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain("Missing or invalid .gitignore at the health-checks cache store path: create the file with '*' on the first line and '!.gitignore' on the second");
});

it('usesSpatieHealthQueueCheckCacheStore passes when fully configured', function () use ($validAppServiceProvider, $validCache, $validGitignore): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    Schedule::command('health:queue-check-heartbeat');

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
        'config/cache.php' => $validCache,
        'storage/framework/cache/health-checks/.gitignore' => $validGitignore,
    ]);

    expect(makeCheck(UsesSpatieHealthQueueCheckCacheStoreCheck::class)->check())->toBe(CheckResult::PASS);
});

it('usesSpatieHealthQueueCheckCacheStore passes when QueueCheck has additional chained methods', function () use ($validCache, $validGitignore): void {
    bindFakeComposer(['spatie/laravel-health' => true]);
    Schedule::command('health:queue-check-heartbeat');

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                QueueCheck::new()->useCacheStore('health-checks')->onQueue(['default', 'notifications']),
            ]);
            PHP,
        'config/cache.php' => $validCache,
        'storage/framework/cache/health-checks/.gitignore' => $validGitignore,
    ]);

    expect(makeCheck(UsesSpatieHealthQueueCheckCacheStoreCheck::class)->check())->toBe(CheckResult::PASS);
});
