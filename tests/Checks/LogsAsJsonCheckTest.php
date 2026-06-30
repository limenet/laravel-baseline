<?php

use Limenet\LaravelBaseline\Checks\Checks\LogsAsJsonCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('logsAsJson passes when a channel uses Laravel JsonFormatter', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'channels' => [
        'stderr' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\StreamHandler::class,
            'formatter' => Illuminate\Log\Formatters\JsonFormatter::class,
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['require' => ['laravel/framework' => '^13.6'], 'scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    $check = makeCheck(LogsAsJsonCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('logsAsJson passes when a channel uses Monolog JsonFormatter', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'channels' => [
        'stderr' => [
            'driver' => 'monolog',
            'handler' => Monolog\Handler\StreamHandler::class,
            'formatter' => Monolog\Formatter\JsonFormatter::class,
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['require' => ['laravel/framework' => '^13.6'], 'scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    $check = makeCheck(LogsAsJsonCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('logsAsJson fails when no channel has a JSON formatter', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'channels' => [
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['require' => ['laravel/framework' => '^13.6'], 'scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    $check = makeCheck(LogsAsJsonCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('logsAsJson fails when config/logging.php is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['require' => ['laravel/framework' => '^13.6'], 'scripts' => []]),
    ]);

    $check = makeCheck(LogsAsJsonCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('logsAsJson provides a helpful comment when no JSON formatter is configured', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'channels' => [
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['require' => ['laravel/framework' => '^13.6'], 'scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    [$check, $collector] = makeCheckWithCollector(LogsAsJsonCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect(collect($collector->all())->contains(fn ($c) => str_contains($c, 'No log channel uses a JSON formatter')))->toBeTrue();
});

it('logsAsJson auto-fix adds a json channel and passes, preserving existing channels', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'channels' => [
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['require' => ['laravel/framework' => '^13.6'], 'scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    $check = makeCheck(LogsAsJsonCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);

    expect($check->fix())->toBe(CheckResult::PASS);

    $contents = file_get_contents(base_path('config/logging.php'));
    // The fix added a json channel using the Laravel JsonFormatter...
    expect($contents)->toContain("'json'");
    expect($contents)->toContain('Illuminate\Log\Formatters\JsonFormatter::class');
    // ...without disturbing the user's existing daily channel or default.
    expect($contents)->toContain("'daily'");
    expect($contents)->toContain("env('LOG_CHANNEL', 'stack')");

    // And re-running the check on the fixed file passes.
    expect(makeCheck(LogsAsJsonCheck::class)->check())->toBe(CheckResult::PASS);
});

it('logsAsJson auto-fix is idempotent', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'channels' => [
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['require' => ['laravel/framework' => '^13.6'], 'scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    expect(makeCheck(LogsAsJsonCheck::class)->fix())->toBe(CheckResult::PASS);
    $afterFirst = file_get_contents(base_path('config/logging.php'));

    // Fixing again must not add a second json channel.
    expect(makeCheck(LogsAsJsonCheck::class)->fix())->toBe(CheckResult::PASS);
    $afterSecond = file_get_contents(base_path('config/logging.php'));

    expect($afterSecond)->toBe($afterFirst);
    expect(substr_count((string) $afterSecond, "'json' =>"))->toBe(1);
});

it('logsAsJson warns on Laravel older than 13.6 and does not touch the config', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'channels' => [
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['require' => ['laravel/framework' => '^12.0'], 'scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    expect(makeCheck(LogsAsJsonCheck::class)->check())->toBe(CheckResult::WARN);

    // The auto-fix must be a no-op on an unsupported version.
    expect(makeCheck(LogsAsJsonCheck::class)->fix())->toBe(CheckResult::WARN);
    expect(file_get_contents(base_path('config/logging.php')))->toBe($loggingConfig);
});
