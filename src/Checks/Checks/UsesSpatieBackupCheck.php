<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Backup\BackupConfigValidator;
use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Concerns\InteractsWithLaravelSchedule;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesSpatieBackupCheck extends AbstractCheck
{
    use InteractsWithLaravelSchedule;

    public function check(): CheckResult
    {
        $scheduleResult = $this->checkPackageWithSchedule(
            'spatie/laravel-backup',
            ['backup:run', 'backup:clean'],
        );

        // If package is not installed, return FAIL (mandatory check)
        if ($scheduleResult === CheckResult::WARN) {
            $this->addComment('Missing package: Install spatie/laravel-backup');

            return CheckResult::FAIL;
        }

        // If schedule checks failed, return FAIL
        if ($scheduleResult === CheckResult::FAIL) {
            return CheckResult::FAIL;
        }

        // Validate the backup configuration file
        $validator = new BackupConfigValidator;
        $errors = $validator->validate(
            $this->path('config/backup.php'),
            checkVerifyBackup: $this->composerPackageSatisfies('spatie/laravel-backup', '^10'),
        );

        foreach ($errors as $error) {
            $this->addComment($error);
        }

        return $errors === [] ? CheckResult::PASS : CheckResult::FAIL;
    }
}
