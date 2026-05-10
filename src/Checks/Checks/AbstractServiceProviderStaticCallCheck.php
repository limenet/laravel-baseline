<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\ServiceProvider\StaticCallVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

abstract class AbstractServiceProviderStaticCallCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $file = base_path('app/Providers/AppServiceProvider.php');

        if (!file_exists($file)) {
            $this->addComment($this->missingCallComment());

            return CheckResult::FAIL;
        }

        $parser = (new ParserFactory())->createForNewestSupportedVersion();

        try {
            $ast = $parser->parse(file_get_contents($file) ?: '') ?? [];
        } catch (\Throwable) {
            return CheckResult::FAIL;
        }

        $visitor = new StaticCallVisitor($this->staticClassName(), $this->staticMethodName());
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        if (!$visitor->wasFound()) {
            $this->addComment($this->missingCallComment());

            return CheckResult::FAIL;
        }

        if (!$visitor->isValid()) {
            $this->addComment($this->falseLiteralComment());

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    abstract protected function staticClassName(): string;

    abstract protected function staticMethodName(): string;

    abstract protected function missingCallComment(): string;

    abstract protected function falseLiteralComment(): string;
}
