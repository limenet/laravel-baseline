<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Health\HealthChecksStaticCallVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

abstract class AbstractUsesSpatieHealthChecksCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        if (!$this->checkComposerPackages($this->requiredComposerPackages())) {
            return CheckResult::WARN;
        }

        $file = base_path('app/Providers/AppServiceProvider.php');

        if (!file_exists($file)) {
            $this->addComment($this->missingChecksComment());

            return CheckResult::FAIL;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse(file_get_contents($file) ?: '') ?? [];
        } catch (\Throwable) {
            return CheckResult::FAIL;
        }

        $visitor = new HealthChecksStaticCallVisitor($this->requiredHealthCheckClasses());
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        if (!$visitor->wasFound()) {
            $this->addComment($this->missingChecksComment());

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    /** @return list<string> Short class names required in Health::checks([...]) */
    abstract protected function requiredHealthCheckClasses(): array;

    protected function missingChecksComment(): string
    {
        $classes = implode(', ', $this->requiredHealthCheckClasses());

        return "Health checks not registered: Add Health::checks([{$classes}]) in AppServiceProvider";
    }

    /** @return list<string> */
    protected function requiredComposerPackages(): array
    {
        return ['spatie/laravel-health'];
    }
}
