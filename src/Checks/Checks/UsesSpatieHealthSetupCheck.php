<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Backup\BackupConfigVisitor;
use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Health\HealthConfigVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

class UsesSpatieHealthSetupCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages(['spatie/laravel-health', 'spatie/cpu-load-health-check'])) {
            $this->addComment('Missing packages: Install spatie/laravel-health and spatie/cpu-load-health-check');

            return CheckResult::FAIL;
        }

        if (!$this->hasScheduleEntry('health:check')) {
            $this->addComment('Missing schedule: Add RunHealthChecksCommand::class scheduled everyThirtyMinutes() in your scheduler');

            return CheckResult::FAIL;
        }

        if (!$this->hasScheduleEntry('health:schedule-check-heartbeat')) {
            $this->addComment('Missing schedule: Add ScheduleCheckHeartbeatCommand::class scheduled everyMinute() in your scheduler');

            return CheckResult::FAIL;
        }

        if (!$this->hasS3HealthDisk()) {
            $this->addComment('Missing s3_health disk: Add s3_health disk definition to config/filesystems.php');

            return CheckResult::FAIL;
        }

        if (!$this->hasHealthResultStoreConfig()) {
            $this->addComment('Missing health result store: Configure JsonFileHealthResultStore with disk s3_health and path health.json in config/health.php, and set notifications.enabled to false');

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    private function hasS3HealthDisk(): bool
    {
        $file = base_path('config/filesystems.php');

        if (!file_exists($file)) {
            return false;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse(file_get_contents($file) ?: '') ?? [];
        } catch (\Throwable) {
            return false;
        }

        $visitor = new BackupConfigVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $config = $visitor->getConfig();

        return isset($config['disks']['s3_health']);
    }

    private function hasHealthResultStoreConfig(): bool
    {
        $file = base_path('config/health.php');

        if (!file_exists($file)) {
            return false;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse(file_get_contents($file) ?: '') ?? [];
        } catch (\Throwable) {
            return false;
        }

        $visitor = new HealthConfigVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->hasValidResultStore() && $visitor->notificationsDisabled();
    }
}
