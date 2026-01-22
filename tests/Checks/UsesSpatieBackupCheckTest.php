<?php

use Illuminate\Support\Facades\Schedule;
use Limenet\LaravelBaseline\Checks\Checks\UsesSpatieBackupCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

it('usesSpatieBackup warns when package not installed', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => false]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::WARN);
});

it('usesSpatieBackup fails when installed but not scheduled', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup fails when scheduled but config file missing', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);
    $this->withTempBasePath(['composer.json' => json_encode(['name' => 'tmp'])]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup fails when backup.name does not use env()', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => 'my-app',
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => [env('DB_CONNECTION', 'mysql')],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup fails when backup.name uses env() with laravel default', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'laravel'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => [env('DB_CONNECTION', 'mysql')],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'laravel'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup fails when cleanup settings are incorrect', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => [env('DB_CONNECTION', 'mysql')],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 4,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => 5000,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup fails when disk configuration mismatches', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => [env('DB_CONNECTION', 'mysql')],
        ],
        'destination' => ['disks' => ['local', 's3']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup fails when follow_links is not true', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => false, 'relative_path' => base_path()],
            'databases' => [env('DB_CONNECTION', 'mysql')],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup fails when relative_path does not use base_path()', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => '/var/www'],
            'databases' => [env('DB_CONNECTION', 'mysql')],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup fails when databases does not match database.default', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => ['mysql'],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    // database.php uses env() but backup.php uses hardcoded 'mysql' - should fail
    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup passes when database.default is a simple string and backup.databases matches', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => ['mysql'],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    // database.php uses a simple string, not env()
    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => 'mysql',
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('usesSpatieBackup fails when database.php is missing', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => ['mysql'],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    // No database.php provided
    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup fails when mail.to does not end with @inbound.postmarkapp.com', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => [env('DB_CONNECTION', 'mysql')],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'admin@example.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup fails when mail.from.address does not use config()', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => [env('DB_CONNECTION', 'mysql')],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => 'noreply@example.com', 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup fails when monitor_backups.name default differs from backup.name default', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => [env('DB_CONNECTION', 'mysql')],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'different-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup passes with valid configuration', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => [env('DB_CONNECTION', 'mysql')],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('usesSpatieBackup passes with valid APP_NAME configuration', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_NAME', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => [env('DB_CONNECTION', 'mysql')],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_NAME', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});

it('usesSpatieBackup fails when monitor_backups uses different env var than backup.name', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_NAME', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => [env('DB_CONNECTION', 'mysql')],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => env('DB_CONNECTION', 'mysql'),
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup fails when database dump config is missing', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => ['mysql'],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'host' => 'localhost',
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup fails when database dump useSingleTransaction is not true', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => ['mysql'],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'dump' => [
                'useSingleTransaction' => false,
                'addExtraOption' => '--hex-blob',
            ],
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup fails when database dump addExtraOption is not --hex-blob', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => ['mysql'],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'dump' => [
                'useSingleTransaction' => true,
                'addExtraOption' => '--other-option',
            ],
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::FAIL);
});

it('usesSpatieBackup passes with valid database dump configuration', function (): void {
    bindFakeComposer(['spatie/laravel-backup' => true]);

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => ['mysql'],
        ],
        'destination' => ['disks' => ['local']],
    ],
    'notifications' => [
        'mail' => [
            'to' => 'test@inbound.postmarkapp.com',
            'from' => ['address' => config('mail.from.address'), 'name' => config('mail.from.name')],
        ],
    ],
    'monitor_backups' => [
        ['name' => env('APP_URL', 'my-app'), 'disks' => ['local']],
    ],
    'cleanup' => [
        'default_strategy' => [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ],
    ],
];
PHP;

    $databaseConfig = <<<'PHP'
<?php
return [
    'default' => 'mysql',
    'connections' => [
        'mysql' => [
            'driver' => 'mysql',
            'dump' => [
                'useSingleTransaction' => true,
                'addExtraOption' => '--hex-blob',
            ],
        ],
    ],
];
PHP;

    $this->withTempBasePath([
        'composer.json' => json_encode(['name' => 'tmp']),
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    Schedule::command('backup:run');
    Schedule::command('backup:clean');
    $check = makeCheck(UsesSpatieBackupCheck::class);
    expect($check->check())->toBe(CheckResult::PASS);
});
