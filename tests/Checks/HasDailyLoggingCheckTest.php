<?php

use Limenet\LaravelBaseline\Checks\Checks\HasDailyLoggingCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('hasDailyLogging passes when default is daily', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'default' => 'daily',
    'channels' => [
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    $check = makeCheck(HasDailyLoggingCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('hasDailyLogging passes when default uses env() with daily fallback', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'default' => env('LOG_CHANNEL', 'daily'),
    'channels' => [
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    $check = makeCheck(HasDailyLoggingCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('hasDailyLogging passes when default is stack with daily in channels', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'default' => 'stack',
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'nightwatch'],
            'ignore_exceptions' => false,
        ],
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    $check = makeCheck(HasDailyLoggingCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('hasDailyLogging passes when default uses env() with stack fallback and daily in channels', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['daily', 'nightwatch'],
            'ignore_exceptions' => false,
        ],
        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    $check = makeCheck(HasDailyLoggingCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('hasDailyLogging fails when default uses env() with stack fallback but daily not in channels', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'default' => env('LOG_CHANNEL', 'stack'),
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'stderr'],
            'ignore_exceptions' => false,
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    $check = makeCheck(HasDailyLoggingCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('Stack channel must include "daily": Update config/logging.php channels.stack.channels to include \'daily\'');
});

it('hasDailyLogging fails when default is stack without daily in channels', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'default' => 'stack',
    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    $check = makeCheck(HasDailyLoggingCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('Stack channel must include "daily": Update config/logging.php channels.stack.channels to include \'daily\'');
});

it('hasDailyLogging fails when default is stack with missing channels array', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'default' => 'stack',
    'channels' => [
        'stack' => [
            'driver' => 'stack',
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    $check = makeCheck(HasDailyLoggingCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('hasDailyLogging fails when default is single', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'default' => 'single',
    'channels' => [
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    $check = makeCheck(HasDailyLoggingCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('hasDailyLogging fails when default uses env() with invalid fallback', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'default' => env('LOG_CHANNEL', 'single'),
    'channels' => [
        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    $check = makeCheck(HasDailyLoggingCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('hasDailyLogging fails when config file is missing', function (): void {
    bindFakeComposer([]);

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
    ]);

    $check = makeCheck(HasDailyLoggingCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
    expect($check->getComments())->toContain('Logging configuration missing: config/logging.php not found');
});

it('hasDailyLogging fails when default key is missing', function (): void {
    bindFakeComposer([]);
    $loggingConfig = <<<'PHP'
<?php

return [
    'channels' => [
        'daily' => [
            'driver' => 'daily',
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['scripts' => []]),
        'config/logging.php' => $loggingConfig,
    ]);

    $check = makeCheck(HasDailyLoggingCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});
