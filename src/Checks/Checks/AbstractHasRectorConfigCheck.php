<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

abstract class AbstractHasRectorConfigCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $rectorConfigFile = base_path('rector.php');

        if (!file_exists($rectorConfigFile)) {
            return CheckResult::FAIL;
        }

        try {
            $code = file_get_contents($rectorConfigFile) ?: throw new \RuntimeException();
            $parser = (new ParserFactory())->createForNewestSupportedVersion();
            $ast = $parser->parse($code) ?: throw new \RuntimeException();
        } catch (\Throwable) {
            return CheckResult::FAIL;
        }

        $visitor = $this->makeVisitor();
        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        if (!$visitor->wasFound()) {
            $this->addComment($visitor->getErrorMessage());

            return CheckResult::FAIL;
        }

        return CheckResult::PASS;
    }

    abstract protected function makeVisitor(): AbstractRectorVisitor;
}
