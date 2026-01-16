<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractCheck;
use Limenet\LaravelBaseline\Checks\CommentCollector;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Rector\RectorVisitorArrayArgument;
use Limenet\LaravelBaseline\Rector\RectorVisitorClassFetch;
use Limenet\LaravelBaseline\Rector\RectorVisitorHasCall;
use Limenet\LaravelBaseline\Rector\RectorVisitorNamedArgument;
use Limenet\LaravelBaseline\Rector\RectorVisitorPaths;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

class HasCompleteRectorConfigurationCheck extends AbstractCheck
{
    public function check(): CheckResult
    {
        $rectorConfigFile = base_path('rector.php');

        if (!file_exists($rectorConfigFile)) {
            return CheckResult::FAIL;
        }

        $code = file_get_contents($rectorConfigFile) ?: throw new \RuntimeException();

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code) ?: throw new \RuntimeException();

        $traverser = new NodeTraverser();

        $visitors = [
            new RectorVisitorNamedArgument($this->commentCollector, 'withComposerBased', ['phpunit', 'symfony', 'laravel']),
            new RectorVisitorNamedArgument($this->commentCollector, 'withPreparedSets', ['deadCode', 'codeQuality', 'codingStyle', 'typeDeclarations', 'privatization', 'instanceOf', 'earlyReturn']),
            new RectorVisitorNamedArgument($this->commentCollector, 'withImportNames', ['!importShortClasses']),
            new RectorVisitorHasCall($this->commentCollector, 'withPhpSets'),
            new RectorVisitorHasCall($this->commentCollector, 'withAttributesSets'),
            new RectorVisitorClassFetch($this->commentCollector, 'withSetProviders', ['LaravelSetProvider']),
            new RectorVisitorArrayArgument($this->commentCollector, 'withRules', ['AddGenericReturnTypeToRelationsRector']),
            // new RectorVisitorArrayArgument($this->commentCollector, 'withSkip', ['FunctionLikeToFirstClassCallableRector']),
            new RectorVisitorPaths($this->commentCollector, 'withPaths', ['app', 'database', 'routes', 'tests']),
        ];

        foreach ($visitors as $visitor) {
            $traverser->addVisitor($visitor);
        }

        $traverser->traverse($ast);

        foreach ($visitors as $visitor) {
            if (!$visitor->wasFound()) {
                $this->addComment($visitor->getErrorMessage());

                return CheckResult::FAIL;
            }
        }

        return CheckResult::PASS;
    }
}
