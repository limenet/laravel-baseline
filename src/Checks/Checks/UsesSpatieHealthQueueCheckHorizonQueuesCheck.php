<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Backup\BackupConfigVisitor;
use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Health\QueueCheckHorizonQueuesVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

class UsesSpatieHealthQueueCheckHorizonQueuesCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages(['spatie/laravel-health', 'laravel/horizon'])) {
            return CheckResult::WARN;
        }

        $horizonQueues = $this->getHorizonQueues();

        if ($horizonQueues === null) {
            $this->addComment('Cannot parse config/horizon.php: ensure the file exists and is valid PHP');

            return CheckResult::FAIL;
        }

        $onQueueQueues = $this->getQueueCheckOnQueueQueues();

        if ($onQueueQueues === null) {
            $missing = implode(', ', $horizonQueues);
            $this->addComment("QueueCheck must register all Horizon queues: add ->onQueue([{$missing}]) to QueueCheck in AppServiceProvider");

            return CheckResult::FAIL;
        }

        $missingQueues = array_values(array_diff($horizonQueues, $onQueueQueues));

        if ($missingQueues !== []) {
            $missing = implode(', ', $missingQueues);
            $this->addComment("QueueCheck is missing Horizon queues: add [{$missing}] to the onQueue call in AppServiceProvider");

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    /** @return list<string>|null null if horizon.php cannot be parsed */
    private function getHorizonQueues(): ?array
    {
        $file = base_path('config/horizon.php');

        if (!file_exists($file)) {
            return null;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse(file_get_contents($file) ?: '') ?? [];
        } catch (\Throwable) {
            return null;
        }

        $visitor = new BackupConfigVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $config = $visitor->getConfig();

        if (!isset($config['environments']) || !is_array($config['environments'])) {
            return null;
        }

        $queues = [];

        foreach ($config['environments'] as $supervisors) {
            if (!is_array($supervisors)) {
                continue;
            }

            foreach ($supervisors as $supervisor) {
                if (!is_array($supervisor) || !isset($supervisor['queue'])) {
                    continue;
                }

                $queue = $supervisor['queue'];

                if (is_string($queue)) {
                    $queues[] = $queue;
                } elseif (is_array($queue)) {
                    foreach ($queue as $q) {
                        if (is_string($q)) {
                            $queues[] = $q;
                        }
                    }
                }
            }
        }

        return array_values(array_unique($queues));
    }

    /** @return list<string>|null null if QueueCheck not found or has no onQueue call */
    private function getQueueCheckOnQueueQueues(): ?array
    {
        $file = base_path('app/Providers/AppServiceProvider.php');

        if (!file_exists($file)) {
            return null;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse(file_get_contents($file) ?: '') ?? [];
        } catch (\Throwable) {
            return null;
        }

        $visitor = new QueueCheckHorizonQueuesVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->getQueues();
    }
}
