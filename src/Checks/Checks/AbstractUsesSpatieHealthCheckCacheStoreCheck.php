<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Backup\BackupConfigVisitor;
use Limenet\LaravelBaseline\Backup\FuncCallInfo;
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
            $this->addComment("{$class} must use the dedicated cache store: change {$class}::new() to {$class}::new()->{$this->cacheStoreMethod()}('health-checks') in AppServiceProvider");

            return CheckResult::FAIL;
        }

        if (!$this->hasHealthChecksCacheStore()) {
            $this->addComment("Missing health-checks cache store: add a 'health-checks' entry with driver 'file' under 'stores' in config/cache.php");

            return CheckResult::FAIL;
        }

        if (!$this->hasHealthChecksCacheStorePath()) {
            $this->addComment("Incorrect path in health-checks cache store in config/cache.php: set 'path' to storage_path('...')");

            return CheckResult::FAIL;
        }

        if (!$this->hasHealthChecksCacheStoreGitignore()) {
            $this->addComment("Missing or invalid .gitignore at the health-checks cache store path: create the file with '*' on the first line and '!.gitignore' on the second");

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    abstract protected function healthCheckClassName(): string;

    protected function cacheStoreMethod(): string
    {
        return 'useCacheStore';
    }

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

        $visitor = new HealthCheckCacheStoreVisitor($class, 'health-checks', $this->cacheStoreMethod());
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

    protected function hasHealthChecksCacheStorePath(): bool
    {
        return $this->resolveHealthChecksCacheStorePath() !== null;
    }

    protected function hasHealthChecksCacheStoreGitignore(): bool
    {
        $path = $this->resolveHealthChecksCacheStorePath();

        if ($path === null) {
            return false;
        }

        $file = $path.'/.gitignore';

        if (!file_exists($file)) {
            return false;
        }

        $content = file_get_contents($file);

        if ($content === false) {
            return false;
        }

        $lines = array_values(array_filter(array_map('trim', explode("\n", $content))));

        return ($lines[0] ?? null) === '*' && ($lines[1] ?? null) === '!.gitignore';
    }

    private function resolveHealthChecksCacheStorePath(): ?string
    {
        $file = base_path('config/cache.php');

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

        $store = $visitor->getConfig()['stores']['health-checks'] ?? null;

        if (!is_array($store)) {
            return null;
        }

        $path = $store['path'] ?? null;

        if (!$path instanceof FuncCallInfo || $path->name !== 'storage_path' || !is_string($path->getFirstArg())) {
            return null;
        }

        return storage_path($path->getFirstArg());
    }
}
