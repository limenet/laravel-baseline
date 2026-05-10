<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter;

abstract class AbstractHasRectorConfigCheck extends AbstractFixableCheck
{
    public function fix(bool $dry = false): CheckResult
    {
        $rectorFile = base_path('rector.php');

        if (!file_exists($rectorFile)) {
            if ($dry) {
                return CheckResult::FAIL;
            }

            file_put_contents($rectorFile, "<?php\n\nuse Rector\\Config\\RectorConfig;\n\nreturn RectorConfig::configure();\n");
        }

        $result = $this->runVisitorOnRector($this->makeVisitor());

        if ($result === null) {
            return CheckResult::PASS;
        }

        if ($dry) {
            return $result;
        }

        // Check if the method is already called (wrong args) — can't safely rewrite
        $snippet = $this->fixCodeSnippet();
        $methodName = ltrim((string) str($snippet)->before('('), '->');

        if (str_contains((string) (file_get_contents($rectorFile) ?: ''), $methodName.'(')) {
            return CheckResult::FAIL;
        }

        $this->appendToRectorChain($rectorFile, $snippet, $this->fixImports());

        return $this->fix(dry: true);
    }

    abstract protected function makeVisitor(): AbstractRectorVisitor;

    abstract protected function fixCodeSnippet(): string;

    /**
     * @return list<string>
     */
    protected function fixImports(): array
    {
        return [];
    }

    protected function runVisitorOnRector(AbstractRectorVisitor $visitor): ?CheckResult
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

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        if (!$visitor->wasFound()) {
            $this->addComment($visitor->getErrorMessage());

            return CheckResult::FAIL;
        }

        return null;
    }

    /**
     * @param  list<string>  $imports
     */
    protected function appendToRectorChain(string $rectorFile, string $snippet, array $imports = []): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $printer = new PrettyPrinter\Standard();

        $code = file_get_contents($rectorFile) ?: '';
        $ast = $parser->parse($code) ?? [];

        $snippetCode = '<?php $dummy'.$snippet.';';
        $snippetAst = $parser->parse($snippetCode) ?? [];

        if ($snippetAst === [] || !$snippetAst[0] instanceof Node\Stmt\Expression) {
            return;
        }

        $methodCall = $snippetAst[0]->expr;

        if (!$methodCall instanceof Node\Expr\MethodCall) {
            return;
        }

        $finder = new NodeFinder();
        $return = $finder->findFirst($ast, fn ($n): bool => $n instanceof Node\Stmt\Return_);

        if ($return instanceof Node\Stmt\Return_) {
            if ($return->expr instanceof Node\Expr\MethodCall || $return->expr instanceof Node\Expr\StaticCall) {
                $methodCall->var = $return->expr;
                $return->expr = $methodCall;
            } else {
                $exprStmt = $finder->findFirst($ast, fn ($n): bool => $n instanceof Node\Stmt\Expression
                    && $n->expr instanceof Node\Expr\MethodCall);

                if ($exprStmt instanceof Node\Stmt\Expression) {
                    $methodCall->var = $exprStmt->expr;
                    $exprStmt->expr = $methodCall;
                }
            }
        }

        $this->addMissingUseStatements($ast, $imports);

        file_put_contents($rectorFile, $printer->prettyPrintFile($ast));
    }

    /**
     * @param  array<Node>  $ast
     * @param  list<string>  $imports
     */
    private function addMissingUseStatements(array &$ast, array $imports): void
    {
        if ($imports === []) {
            return;
        }

        $existingFqns = [];
        $lastUseIdx = -1;

        foreach ($ast as $i => $stmt) {
            if ($stmt instanceof Node\Stmt\Use_) {
                $lastUseIdx = $i;

                foreach ($stmt->uses as $use) {
                    $existingFqns[] = $use->name->toString();
                }
            }
        }

        $newUses = [];

        foreach ($imports as $fqn) {
            if (!in_array($fqn, $existingFqns, true)) {
                $newUses[] = new Node\Stmt\Use_([
                    new Node\UseItem(new Node\Name($fqn)),
                ]);
            }
        }

        if ($newUses !== []) {
            array_splice($ast, $lastUseIdx + 1, 0, $newUses);
        }
    }
}
