<?php

namespace Limenet\LaravelBaseline\Backup;

use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

class BackupConfigValidator
{
    /**
     * @var list<string>
     */
    private array $errors = [];

    /**
     * @var array<string|int, mixed>
     */
    private array $config = [];

    /**
     * @var array<string|int, mixed>
     */
    private array $databaseConfig = [];

    /**
     * Validate the backup configuration file.
     *
     * @return list<string> List of validation errors
     */
    public function validate(string $configPath, ?string $databaseConfigPath = null): array
    {
        $this->errors = [];

        if (!file_exists($configPath)) {
            $this->errors[] = 'Backup configuration missing: Create config/backup.php by running "php artisan vendor:publish --provider=\"Spatie\\Backup\\BackupServiceProvider\""';

            return $this->errors;
        }

        $code = file_get_contents($configPath);

        if ($code === false) {
            $this->errors[] = 'Backup configuration unreadable: Unable to read config/backup.php';

            return $this->errors;
        }

        $this->config = $this->parseConfig($code);

        if ($this->config === []) {
            $this->errors[] = 'Backup configuration invalid: Unable to parse config/backup.php';

            return $this->errors;
        }

        // Parse database config if path provided, otherwise derive from backup config path
        $databaseConfigPath ??= dirname($configPath).'/database.php';
        $this->databaseConfig = $this->parseDatabaseConfig($databaseConfigPath);

        $this->validateBackupName();
        $this->validateCleanupSettings();
        $this->validateDiskConsistency();
        $this->validateSourceSettings();
        $this->validateNotificationMail();

        return $this->errors;
    }

    /**
     * Parse the database configuration file.
     *
     * @return array<string|int, mixed>
     */
    private function parseDatabaseConfig(string $path): array
    {
        if (!file_exists($path)) {
            return [];
        }

        $code = file_get_contents($path);

        if ($code === false) {
            return [];
        }

        return $this->parseConfig($code);
    }

    /**
     * Parse the config file using PHP Parser.
     *
     * @return array<string|int, mixed>
     */
    private function parseConfig(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse($code);
        } catch (\Throwable) {
            return [];
        }

        if ($ast === null) {
            return [];
        }

        $visitor = new BackupConfigVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getConfig();
    }

    /**
     * Get a value at a nested path using dot notation.
     */
    private function getValueAt(string $path): mixed
    {
        $keys = explode('.', $path);
        $current = $this->config;

        foreach ($keys as $key) {
            if (!is_array($current) || !array_key_exists($key, $current)) {
                return null;
            }
            $current = $current[$key];
        }

        return $current;
    }

    /**
     * Validate backup name settings.
     */
    private function validateBackupName(): void
    {
        $backupName = $this->getValueAt('backup.name');

        // Check backup.name uses env('APP_NAME', ...) or env('APP_URL', ...)
        if (!$backupName instanceof FuncCallInfo) {
            $this->errors[] = 'Backup name must use env() function: Set backup.name to env(\'APP_NAME\', \'your-app-name\') or env(\'APP_URL\', \'your-app-url\')';

            return;
        }

        $allowedEnvVars = ['APP_NAME', 'APP_URL'];
        $usedEnvVar = $backupName->getFirstArg();

        if (!$backupName->isCall('env') || !in_array($usedEnvVar, $allowedEnvVars, true)) {
            $this->errors[] = 'Backup name must use APP_NAME or APP_URL environment variable: Set backup.name to env(\'APP_NAME\', \'your-app-name\') or env(\'APP_URL\', \'your-app-url\')';

            return;
        }

        $defaultValue = $backupName->getSecondArg();

        if (!is_string($defaultValue) || $defaultValue === '') {
            $this->errors[] = 'Backup name env() must have a non-empty default value: Set backup.name to env(\'APP_NAME\', \'your-app-name\') or env(\'APP_URL\', \'your-app-url\')';

            return;
        }

        if (strtolower($defaultValue) === 'laravel') {
            $this->errors[] = 'Backup name env() default must not be "laravel": Set backup.name to env(\'APP_NAME\', \'your-actual-app-name\') or env(\'APP_URL\', \'your-actual-app-url\')';

            return;
        }

        // Check monitor_backups consistency
        $monitorBackups = $this->getValueAt('monitor_backups');

        if (is_array($monitorBackups)) {
            foreach ($monitorBackups as $index => $monitor) {
                if (!is_array($monitor)) {
                    continue;
                }

                $monitorName = $monitor['name'] ?? null;

                // Monitor name should also use the same env variable
                if ($monitorName instanceof FuncCallInfo) {
                    $monitorEnvVar = $monitorName->getFirstArg();

                    if (!$monitorName->isCall('env') || !in_array($monitorEnvVar, $allowedEnvVars, true)) {
                        $this->errors[] = sprintf(
                            'Monitor backup name mismatch at index %s: monitor_backups.%s.name must use env(\'APP_NAME\', ...) or env(\'APP_URL\', ...)',
                            $index,
                            $index,
                        );
                    } elseif ($monitorEnvVar !== $usedEnvVar) {
                        $this->errors[] = sprintf(
                            'Monitor backup name env var mismatch at index %s: monitor_backups.%s.name must use the same env variable as backup.name (%s)',
                            $index,
                            $index,
                            $usedEnvVar,
                        );
                    } elseif ($monitorName->getSecondArg() !== $defaultValue) {
                        $this->errors[] = sprintf(
                            'Monitor backup name default mismatch at index %s: monitor_backups.%s.name default ("%s") must match backup.name default ("%s")',
                            $index,
                            $index,
                            $monitorName->getSecondArg() ?? 'null',
                            $defaultValue,
                        );
                    }
                } elseif ($monitorName !== null) {
                    $this->errors[] = sprintf(
                        'Monitor backup name must use env() at index %s: Set monitor_backups.%s.name to env(\'APP_NAME\', \'your-app-name\') or env(\'APP_URL\', \'your-app-url\')',
                        $index,
                        $index,
                    );
                }
            }
        }
    }

    /**
     * Validate cleanup strategy settings.
     */
    private function validateCleanupSettings(): void
    {
        $expectedValues = [
            'keep_all_backups_for_days' => 7,
            'keep_daily_backups_for_days' => 16,
            'keep_weekly_backups_for_weeks' => 8,
            'keep_monthly_backups_for_months' => 4,
            'keep_yearly_backups_for_years' => 2,
            'delete_oldest_backups_when_using_more_megabytes_than' => null,
        ];

        foreach ($expectedValues as $key => $expected) {
            $path = 'cleanup.default_strategy.'.$key;
            $actual = $this->getValueAt($path);

            if ($actual !== $expected) {
                $expectedStr = $expected === null ? 'null' : (string) $expected;
                $actualStr = match (true) {
                    $actual === null => 'null',
                    is_bool($actual) => $actual ? 'true' : 'false',
                    is_scalar($actual) => (string) $actual,
                    default => 'unknown',
                };

                $this->errors[] = sprintf(
                    'Backup cleanup setting incorrect: Set %s to %s (found: %s)',
                    $path,
                    $expectedStr,
                    $actualStr,
                );
            }
        }
    }

    /**
     * Validate disk consistency between backup and monitor_backups.
     */
    private function validateDiskConsistency(): void
    {
        $backupDisks = $this->getValueAt('backup.destination.disks');

        if (!is_array($backupDisks)) {
            $this->errors[] = 'Backup destination disks must be an array: Set backup.destination.disks to an array of disk names';

            return;
        }

        $monitorBackups = $this->getValueAt('monitor_backups');

        if (!is_array($monitorBackups)) {
            return;
        }

        foreach ($monitorBackups as $index => $monitor) {
            if (!is_array($monitor)) {
                continue;
            }

            $monitorDisks = $monitor['disks'] ?? null;

            if (!is_array($monitorDisks)) {
                $this->errors[] = sprintf(
                    'Monitor backup disks must be an array at index %s: Set monitor_backups.%s.disks to an array',
                    $index,
                    $index,
                );

                continue;
            }

            // Compare disk arrays
            $backupDisksSorted = $backupDisks;
            $monitorDisksSorted = $monitorDisks;
            sort($backupDisksSorted);
            sort($monitorDisksSorted);

            if ($backupDisksSorted !== $monitorDisksSorted) {
                $this->errors[] = sprintf(
                    'Disk configuration mismatch at index %s: monitor_backups.%s.disks must match backup.destination.disks',
                    $index,
                    $index,
                );
            }
        }
    }

    /**
     * Validate source settings.
     */
    private function validateSourceSettings(): void
    {
        // Check follow_links
        $followLinks = $this->getValueAt('backup.source.files.follow_links');

        if ($followLinks !== true) {
            $this->errors[] = 'Backup source follow_links must be true: Set backup.source.files.follow_links to true';
        }

        // Check relative_path uses base_path()
        $relativePath = $this->getValueAt('backup.source.files.relative_path');

        if (!$relativePath instanceof FuncCallInfo || !$relativePath->isCall('base_path')) {
            $this->errors[] = 'Backup relative_path must use base_path(): Set backup.source.files.relative_path to base_path()';
        }

        // Check databases matches database.default from database.php
        $databases = $this->getValueAt('backup.source.databases');

        if (!is_array($databases)) {
            $this->errors[] = 'Backup source databases must be an array: Set backup.source.databases to an array';

            return;
        }

        $databaseDefault = $this->databaseConfig['default'] ?? null;

        if ($databaseDefault === null) {
            $this->errors[] = 'Unable to determine database default: Ensure config/database.php exists and has a \'default\' key';

            return;
        }

        $hasMatchingDatabase = false;

        foreach ($databases as $db) {
            if ($this->valuesMatch($db, $databaseDefault)) {
                $hasMatchingDatabase = true;
                break;
            }
        }

        if (!$hasMatchingDatabase) {
            $expectedValue = $this->formatExpectedValue($databaseDefault);
            $this->errors[] = sprintf(
                'Backup source databases must include the database default: Add %s to backup.source.databases array (must match database.default from config/database.php)',
                $expectedValue,
            );
        }
    }

    /**
     * Validate notification mail settings.
     */
    private function validateNotificationMail(): void
    {
        // Check mail.to ends with @inbound.postmarkapp.com
        $mailTo = $this->getValueAt('notifications.mail.to');

        if (!is_string($mailTo)) {
            $this->errors[] = 'Backup notification mail.to must be a string: Set notifications.mail.to to an @inbound.postmarkapp.com address';
        } elseif (!str_ends_with($mailTo, '@inbound.postmarkapp.com')) {
            $this->errors[] = 'Backup notification mail.to must end with @inbound.postmarkapp.com: Update notifications.mail.to';
        }

        // Check mail.from.address uses config('mail.from.address')
        $fromAddress = $this->getValueAt('notifications.mail.from.address');

        if (!$fromAddress instanceof FuncCallInfo || !$fromAddress->isCall('config', 'mail.from.address')) {
            $this->errors[] = 'Backup notification mail.from.address must use config(\'mail.from.address\'): Set notifications.mail.from.address to config(\'mail.from.address\')';
        }

        // Check mail.from.name uses config('mail.from.name')
        $fromName = $this->getValueAt('notifications.mail.from.name');

        if (!$fromName instanceof FuncCallInfo || !$fromName->isCall('config', 'mail.from.name')) {
            $this->errors[] = 'Backup notification mail.from.name must use config(\'mail.from.name\'): Set notifications.mail.from.name to config(\'mail.from.name\')';
        }
    }

    /**
     * Check if two values match (handles FuncCallInfo comparison).
     */
    private function valuesMatch(mixed $a, mixed $b): bool
    {
        // Both are FuncCallInfo - compare function name and arguments
        if ($a instanceof FuncCallInfo && $b instanceof FuncCallInfo) {
            return $a->name === $b->name && $a->args === $b->args;
        }

        // Both are scalar values
        if (is_string($a) && is_string($b)) {
            return $a === $b;
        }

        return false;
    }

    /**
     * Format a value for display in error messages.
     */
    private function formatExpectedValue(mixed $value): string
    {
        if ($value instanceof FuncCallInfo) {
            $args = array_map(
                fn ($arg) => is_string($arg) ? "'{$arg}'" : (string) $arg,
                $value->args,
            );

            return $value->name.'('.implode(', ', $args).')';
        }

        if (is_string($value)) {
            return "'{$value}'";
        }

        return (string) $value;
    }
}
