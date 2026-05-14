<?php

use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieHealthQueueCheckHorizonQueuesCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

$validHorizon = <<<'PHP'
<?php
return ['environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['default', 'notifications'],
        ],
    ],
]];
PHP;

$validAppServiceProvider = <<<'PHP'
<?php
Health::checks([
    QueueCheck::new()->useCacheStore('health-checks')->onQueue(['default', 'notifications']),
]);
PHP;

it('usesSpatieHealthQueueCheckHorizonQueues warns when packages are not installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => false, 'laravel/horizon' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesSpatieHealthQueueCheckHorizonQueuesCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesSpatieHealthQueueCheckHorizonQueues warns when only spatie/laravel-health is installed', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'laravel/horizon' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    expect(makeCheck(UsesSpatieHealthQueueCheckHorizonQueuesCheck::class)->check())->toBe(CheckResult::WARN);
});

it('usesSpatieHealthQueueCheckHorizonQueues fails when config/horizon.php is missing', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'laravel/horizon' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthQueueCheckHorizonQueuesCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('Cannot parse config/horizon.php: ensure the file exists and is valid PHP');
});

it('usesSpatieHealthQueueCheckHorizonQueues fails when QueueCheck has no onQueue call', function () use ($validHorizon): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'laravel/horizon' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/horizon.php' => $validHorizon,
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                QueueCheck::new()->useCacheStore('health-checks'),
            ]);
            PHP,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthQueueCheckHorizonQueuesCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('QueueCheck must register all Horizon queues: add ->onQueue([default, notifications]) to QueueCheck in AppServiceProvider');
});

it('usesSpatieHealthQueueCheckHorizonQueues fails when onQueue is missing a Horizon queue', function () use ($validHorizon): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'laravel/horizon' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/horizon.php' => $validHorizon,
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                QueueCheck::new()->useCacheStore('health-checks')->onQueue(['default']),
            ]);
            PHP,
    ]);

    [$check, $collector] = makeCheckWithCollector(UsesSpatieHealthQueueCheckHorizonQueuesCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($collector->all())->toContain('QueueCheck is missing Horizon queues: add [notifications] to the onQueue call in AppServiceProvider');
});

it('usesSpatieHealthQueueCheckHorizonQueues passes when all Horizon queues are covered', function () use ($validHorizon, $validAppServiceProvider): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'laravel/horizon' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/horizon.php' => $validHorizon,
        'app/Providers/AppServiceProvider.php' => $validAppServiceProvider,
    ]);

    expect(makeCheck(UsesSpatieHealthQueueCheckHorizonQueuesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('usesSpatieHealthQueueCheckHorizonQueues passes when Horizon config has multiple supervisors and environments', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'laravel/horizon' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/horizon.php' => <<<'PHP'
            <?php
            return ['environments' => [
                'production' => [
                    'supervisor-1' => ['connection' => 'redis', 'queue' => ['default']],
                    'supervisor-2' => ['connection' => 'redis', 'queue' => ['notifications', 'exports']],
                ],
                'local' => [
                    'supervisor-1' => ['connection' => 'redis', 'queue' => ['default', 'notifications']],
                ],
            ]];
            PHP,
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                QueueCheck::new()->useCacheStore('health-checks')->onQueue(['default', 'notifications', 'exports']),
            ]);
            PHP,
    ]);

    expect(makeCheck(UsesSpatieHealthQueueCheckHorizonQueuesCheck::class)->check())->toBe(CheckResult::PASS);
});

it('usesSpatieHealthQueueCheckHorizonQueues passes with single string queue in horizon config', function (): void {
    bindFakeComposer(['spatie/laravel-health' => true, 'laravel/horizon' => true]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/horizon.php' => <<<'PHP'
            <?php
            return ['environments' => [
                'production' => [
                    'supervisor-1' => ['connection' => 'redis', 'queue' => 'default'],
                ],
            ]];
            PHP,
        'app/Providers/AppServiceProvider.php' => <<<'PHP'
            <?php
            Health::checks([
                QueueCheck::new()->useCacheStore('health-checks')->onQueue('default'),
            ]);
            PHP,
    ]);

    expect(makeCheck(UsesSpatieHealthQueueCheckHorizonQueuesCheck::class)->check())->toBe(CheckResult::PASS);
});
