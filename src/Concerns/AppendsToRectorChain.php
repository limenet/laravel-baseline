<?php

namespace Limenet\LaravelBaseline\Concerns;

use Limenet\LaravelBaseline\PhpFile\PhpFileWriter;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;

/**
 * Splices a method call onto the end of rector.php's RectorConfig chain.
 */
trait AppendsToRectorChain
{
    /**
     * @param  list<string>  $imports
     */
    protected function appendToRectorChain(string $rectorFile, string $snippet, array $imports = []): void
    {
        $snippetCode = '<?php $dummy'.$snippet.';';
        $snippetAst = (new ParserFactory)->createForNewestSupportedVersion()->parse($snippetCode) ?? [];

        if ($snippetAst === [] || !$snippetAst[0] instanceof Node\Stmt\Expression) {
            return;
        }

        $methodCall = $snippetAst[0]->expr;

        if (!$methodCall instanceof Node\Expr\MethodCall) {
            return;
        }

        $writer = PhpFileWriter::open($rectorFile);
        $finder = new NodeFinder;
        $return = $finder->findFirst($writer->stmts, fn ($n): bool => $n instanceof Node\Stmt\Return_);

        if ($return instanceof Node\Stmt\Return_) {
            if ($return->expr instanceof Node\Expr\MethodCall || $return->expr instanceof Node\Expr\StaticCall) {
                $methodCall->var = $return->expr;
                $return->expr = $methodCall;
            } else {
                $exprStmt = $finder->findFirst($writer->stmts, fn ($n): bool => $n instanceof Node\Stmt\Expression
                    && $n->expr instanceof Node\Expr\MethodCall);

                if ($exprStmt instanceof Node\Stmt\Expression) {
                    $methodCall->var = $exprStmt->expr;
                    $exprStmt->expr = $methodCall;
                }
            }
        }

        $writer->addMissingUseStatements($imports);
        $writer->save();
    }
}
