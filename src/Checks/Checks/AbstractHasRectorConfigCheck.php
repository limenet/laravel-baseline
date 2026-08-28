<?php

namespace Limenet\LaravelBaseline\Checks\Checks;

use Limenet\LaravelBaseline\Checks\AbstractFixableCheck;
use Limenet\LaravelBaseline\Enums\CheckResult;
use Limenet\LaravelBaseline\PhpFile\PhpFileWriter;
use Limenet\LaravelBaseline\Rector\AbstractRectorVisitor;
use PhpParser\Node;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;

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
            $code = file_get_contents($rectorConfigFile) ?: throw new \RuntimeException;
            $parser = (new ParserFactory)->createForNewestSupportedVersion();
            $ast = $parser->parse($code) ?: throw new \RuntimeException;
        } catch (\Throwable) {
            return CheckResult::FAIL;
        }

        $traverser = new NodeTraverser;
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        if (!$visitor->wasFound()) {
            $this->addComment($visitor->getErrorMessage());

            return CheckResult::FAIL;
        }

        return null;
    }

    /**
     * Add class-constant entries to an array argument that is already there —
     * `->withSkip([...])` exists but is missing a class, say. Returns false when
     * there is no such call to merge into, and nothing needed importing.
     *
     * An import is written for every entry that ends up referenced by a bare
     * short name with no use-statement behind it. That covers the classes just
     * appended, and repairs the ones an older version of this fix wrote without
     * imports — Rector rejects the whole config over those ("These rules from
     * skip() do not exist"). An entry spelled out in full needs no import and
     * does not get one.
     *
     * @param  array<string, string>  $classImports  short class name => fully-qualified name
     */
    protected function mergeIntoArrayArgument(string $rectorFile, string $methodName, array $classImports): bool
    {
        $writer = PhpFileWriter::open($rectorFile);

        $array = null;

        foreach ((new NodeFinder)->findInstanceOf($writer->stmts, Node\Expr\MethodCall::class) as $call) {
            if (!$call->name instanceof Node\Identifier || $call->name->toString() !== $methodName) {
                continue;
            }

            $firstArg = $call->args[0] ?? null;

            if ($firstArg instanceof Node\Arg && $firstArg->value instanceof Node\Expr\Array_) {
                $array = $firstArg->value;
                break;
            }
        }

        if (!$array instanceof Node\Expr\Array_) {
            return false;
        }

        // Entries already in the array, and which of them are written as a bare
        // short name rather than spelled out in full.
        $present = [];
        $unqualified = [];

        foreach ($array->items as $item) {
            foreach ([$item->value, $item->key] as $part) {
                if (!$part instanceof Node\Expr\ClassConstFetch || !$part->class instanceof Node\Name) {
                    continue;
                }

                $present[] = $part->class->getLast();

                if ($part->class->isUnqualified()) {
                    $unqualified[] = $part->class->getLast();
                }
            }
        }

        $appended = false;

        foreach ($classImports as $name => $fqn) {
            if (in_array($name, $present, true)) {
                continue;
            }

            $array->items[] = new Node\ArrayItem(
                new Node\Expr\ClassConstFetch(new Node\Name($name), new Node\Identifier('class')),
            );
            $unqualified[] = $name;
            $appended = true;
        }

        $imported = $this->importedShortNames($writer);

        $imports = [];

        foreach ($classImports as $name => $fqn) {
            if (in_array($name, $unqualified, true) && !in_array($name, $imported, true)) {
                $imports[] = $fqn;
            }
        }

        if (!$appended && $imports === []) {
            return false;
        }

        $writer->addMissingUseStatements($imports);
        // The array is reprinted from scratch either way; one entry per line
        // keeps a long withSkip() readable instead of collapsing it.
        $writer->save(multilineArrays: true);

        return true;
    }

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

    /**
     * Short names the file already has a use-statement for.
     *
     * @return list<string>
     */
    private function importedShortNames(PhpFileWriter $writer): array
    {
        $names = [];

        foreach ((new NodeFinder)->findInstanceOf($writer->stmts, Node\Stmt\Use_::class) as $use) {
            foreach ($use->uses as $useItem) {
                $names[] = $useItem->alias instanceof Node\Identifier
                    ? $useItem->alias->toString()
                    : $useItem->name->getLast();
            }
        }

        return $names;
    }
}
