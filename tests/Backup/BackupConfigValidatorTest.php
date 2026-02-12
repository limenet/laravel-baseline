<?php

use Limenet\LaravelBaseline\Backup\BackupConfigValidator;

it('returns error when backup config file is missing', function (): void {
    $validator = new BackupConfigValidator();
    $this->withTempBasePath([]);

    $errors = $validator->validate(base_path('config/backup.php'));

    expect($errors)->toContain('Backup configuration missing: Create config/backup.php by running "php artisan vendor:publish --provider=\"Spatie\\Backup\\BackupServiceProvider\""');
});

it('returns error when backup config has no return statement', function (): void {
    $validator = new BackupConfigValidator();

    $this->withTempBasePath([
        'config/backup.php' => '<?php // no return',
    ]);

    $errors = $validator->validate(base_path('config/backup.php'));

    expect($errors)->toContain('Backup configuration invalid: Unable to parse config/backup.php');
});

it('validates backup name must use env()', function (): void {
    $validator = new BackupConfigValidator();

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => 'my-app',
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
    'monitor_backups' => [],
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

    $this->withTempBasePath([
        'config/backup.php' => $config,
        'config/database.php' => '<?php return ["default" => "mysql"];',
    ]);

    $errors = $validator->validate(base_path('config/backup.php'));

    expect($errors)->toContain('Backup name must use env() function in config/backup.php: Set backup.name to env(\'APP_NAME\', \'your-app-name\') or env(\'APP_URL\', \'your-app-url\')');
});

it('validates backup name env() uses correct variable', function (): void {
    $validator = new BackupConfigValidator();

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('DB_CONNECTION', 'my-app'),
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
    'monitor_backups' => [],
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

    $this->withTempBasePath([
        'config/backup.php' => $config,
        'config/database.php' => '<?php return ["default" => "mysql"];',
    ]);

    $errors = $validator->validate(base_path('config/backup.php'));

    expect($errors)->toContain('Backup name must use APP_NAME or APP_URL environment variable in config/backup.php: Set backup.name to env(\'APP_NAME\', \'your-app-name\') or env(\'APP_URL\', \'your-app-url\')');
});

it('validates backup name env() has non-empty default', function (): void {
    $validator = new BackupConfigValidator();

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_NAME'),
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
    'monitor_backups' => [],
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

    $this->withTempBasePath([
        'config/backup.php' => $config,
        'config/database.php' => '<?php return ["default" => "mysql"];',
    ]);

    $errors = $validator->validate(base_path('config/backup.php'));

    expect($errors)->toContain('Backup name env() must have a non-empty default value in config/backup.php: Set backup.name to env(\'APP_NAME\', \'your-app-name\') or env(\'APP_URL\', \'your-app-url\')');
});

it('validates monitor backup name uses env() not literal', function (): void {
    $validator = new BackupConfigValidator();

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
        ['name' => 'literal-string', 'disks' => ['local']],
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

    $this->withTempBasePath([
        'config/backup.php' => $config,
        'config/database.php' => '<?php return ["default" => "mysql"];',
    ]);

    $errors = $validator->validate(base_path('config/backup.php'));

    expect(collect($errors)->filter(fn ($e) => str_contains($e, 'Monitor backup name must use env()')))->not->toBeEmpty();
});

it('validates monitor backup name env var matches backup.name env var', function (): void {
    $validator = new BackupConfigValidator();

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_NAME', 'my-app'),
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
        ['name' => env('DB_CONNECTION', 'my-app'), 'disks' => ['local']],
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

    $this->withTempBasePath([
        'config/backup.php' => $config,
        'config/database.php' => '<?php return ["default" => "mysql"];',
    ]);

    $errors = $validator->validate(base_path('config/backup.php'));

    expect(collect($errors)->filter(fn ($e) => str_contains($e, 'Monitor backup name mismatch')))->not->toBeEmpty();
});

it('validates backup destination disks must be an array', function (): void {
    $validator = new BackupConfigValidator();

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => ['mysql'],
        ],
        'destination' => ['disks' => 'local'],
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

    $this->withTempBasePath([
        'config/backup.php' => $config,
        'config/database.php' => '<?php return ["default" => "mysql"];',
    ]);

    $errors = $validator->validate(base_path('config/backup.php'));

    expect($errors)->toContain('Backup destination disks must be an array in config/backup.php: Set backup.destination.disks to an array of disk names');
});

it('validates monitor backup disks must be an array', function (): void {
    $validator = new BackupConfigValidator();

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
        ['name' => env('APP_URL', 'my-app'), 'disks' => 'local'],
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

    $this->withTempBasePath([
        'config/backup.php' => $config,
        'config/database.php' => '<?php return ["default" => "mysql"];',
    ]);

    $errors = $validator->validate(base_path('config/backup.php'));

    expect(collect($errors)->filter(fn ($e) => str_contains($e, 'Monitor backup disks must be an array')))->not->toBeEmpty();
});

it('validates backup source databases must be an array', function (): void {
    $validator = new BackupConfigValidator();

    $config = <<<'PHP'
<?php
return [
    'backup' => [
        'name' => env('APP_URL', 'my-app'),
        'source' => [
            'files' => ['follow_links' => true, 'relative_path' => base_path()],
            'databases' => 'mysql',
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

    $this->withTempBasePath([
        'config/backup.php' => $config,
        'config/database.php' => '<?php return ["default" => "mysql"];',
    ]);

    $errors = $validator->validate(base_path('config/backup.php'));

    expect($errors)->toContain('Backup source databases must be an array in config/backup.php: Set backup.source.databases to an array');
});

it('validates mail.to must be a string', function (): void {
    $validator = new BackupConfigValidator();

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
            'to' => null,
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

    $this->withTempBasePath([
        'config/backup.php' => $config,
        'config/database.php' => '<?php return ["default" => "mysql"];',
    ]);

    $errors = $validator->validate(base_path('config/backup.php'));

    expect($errors)->toContain('Backup notification mail.to must be a string in config/backup.php: Set notifications.mail.to to an @inbound.postmarkapp.com address');
});

it('validates mail.from.name must use config()', function (): void {
    $validator = new BackupConfigValidator();

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
            'from' => ['address' => config('mail.from.address'), 'name' => 'Some Name'],
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

    $this->withTempBasePath([
        'config/backup.php' => $config,
        'config/database.php' => '<?php return ["default" => "mysql"];',
    ]);

    $errors = $validator->validate(base_path('config/backup.php'));

    expect($errors)->toContain('Backup notification mail.from.name must use config(\'mail.from.name\') in config/backup.php: Set notifications.mail.from.name to config(\'mail.from.name\')');
});

it('resolves database connection name from env() call', function (): void {
    $validator = new BackupConfigValidator();

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
        'config/backup.php' => $config,
        'config/database.php' => $databaseConfig,
    ]);

    $errors = $validator->validate(base_path('config/backup.php'));

    expect($errors)->toBe([]);
});
