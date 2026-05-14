<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Backup\BackupConfigVisitor;
use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Health\HealthCheckCacheStoreVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

abstract class AbstractUsesSpatieHealthCheckCacheStoreCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages('spatie/laravel-health')) {
            return CheckResult::WARN;
        }

        $class = $this->healthCheckClassName();

        if (!$this->checkUsesCacheStore($class)) {
            $this->addComment("{$class} must use the dedicated cache store: change {$class}::new() to {$class}::new()->useCacheStore('health-checks') in AppServiceProvider");

            return CheckResult::FAIL;
        }

        if (!$this->hasHealthChecksCacheStore()) {
            $this->addComment("Missing health-checks cache store: add a 'health-checks' entry with driver 'file' under 'stores' in config/cache.php");

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    abstract protected function healthCheckClassName(): string;

    protected function checkUsesCacheStore(string $class): bool
    {
        $file = base_path('app/Providers/AppServiceProvider.php');

        if (!file_exists($file)) {
            return false;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse(file_get_contents($file) ?: '') ?? [];
        } catch (\Throwable) {
            return false;
        }

        $visitor = new HealthCheckCacheStoreVisitor($class, 'health-checks');
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->wasFound();
    }

    protected function hasHealthChecksCacheStore(): bool
    {
        $file = base_path('config/cache.php');

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

        $store = $visitor->getConfig()['stores']['health-checks'] ?? null;

        return is_array($store) && ($store['driver'] ?? null) === 'file';
    }
}
