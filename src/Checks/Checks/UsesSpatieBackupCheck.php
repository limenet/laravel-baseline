<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Backup\BackupConfigValidator;
use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;

class UsesSpatieBackupCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $scheduleResult = $this->checkPackageWithSchedule(
            'spatie/laravel-backup',
            ['backup:run', 'backup:clean'],
        );

        // If package is not installed, return WARN (from checkPackageWithSchedule)
        if ($scheduleResult === CheckResult::WARN) {
            return CheckResult::WARN;
        }

        // If schedule checks failed, return FAIL
        if ($scheduleResult === CheckResult::FAIL) {
            return CheckResult::FAIL;
        }

        // Validate the backup configuration file
        $validator = new BackupConfigValidator();
        $errors = $validator->validate(base_path('config/backup.php'));

        foreach ($errors as $error) {
            $this->addComment($error);
        }

        return $errors === [] ? CheckResult::PASS : CheckResult::FAIL;
    }
}
