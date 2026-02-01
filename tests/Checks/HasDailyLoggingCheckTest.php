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

it('hasDailyLogging fails when default is stack', function (): void {
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
    expect($check->getComments())->toContain('Logging default channel should be "daily": Update config/logging.php to set \'default\' => \'daily\'');
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
